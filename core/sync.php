<?php
// iCal subscription sync.
// Written by Claude, don't judge me ok.

namespace sync;

require_once __DIR__ . "/ical.php";

// Pulls a subscription's iCal feed, mirrors its events into appointments,
// and translates RRULEs into the native (int|cron) recurrence column plus
// the recurrence_until / recurrence_count siblings.
//
// Sync rules:
//   1. Past appointments (ends_at < now) missing from the feed are left
//      alone (historical record).
//   2. Future appointments missing from the feed are deleted. A recurring
//      series is never deleted outright — its past occurrences are history —
//      but ended by clamping recurrence_until to the current moment.
//      Count-limited series are left alone entirely: without expanding the
//      recurrence there is no way to tell whether they already finished,
//      and rewriting them into until-form could resurrect them.
//   3. Appointments still in the feed get their mirrored fields refreshed:
//      title, content, location, meeting, starts_at, ends_at, all_day and
//      the recurrence trio.
//   4. `going`, `urgent` and `color` are user annotations and are never
//      touched.
//
// RRULE handling: recurrences are not expanded into one row per occurrence;
// the recurrence column carries the pattern instead. RRULEs that can't be
// expressed in cron (BYSETPOS, biweekly, '2nd Monday', ...) are dropped —
// the first instance is imported as a single appointment with no recurrence
// and a warning is logged. Modified occurrences (RECURRENCE-ID) aren't
// representable either; the master event wins.

function subscription($id) {
  $subscription = \store\get_subscription($id);
  if(!$subscription) return ['error' => "subscription not found"];

  $feed = \ical\fetch_feed($subscription['url']);
  if($feed === null) return ['error' => "could not read feed"];

  $upstream = [];
  foreach($feed['events'] as $event) {
    if($event['is_exception'] || $event['status'] == 'CANCELLED') continue;
    $upstream[$event['uid']] = normalize_feed_event($event);
  }

  $existing = \store\list_appointments_by_subscription($id) ?: [];

  $now = gmdate('Y-m-d\TH:i:sP');
  $stats = ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'ended' => 0, 'kept' => 0, 'errors' => 0];
  $seen = [];

  foreach($existing as $row) {
    $seen[$row['id']] = true;

    if(isset($upstream[$row['id']])) {
      $data = $upstream[$row['id']];
      if(!appointment_differs($row, $data)) continue;

      \store\update_appointment_body(
        $row['id'],
        $data['title'],
        $data['content'],
        $data['starts_at'],
        $data['ends_at'],
        $data['location'],
        $data['meeting'],
        $data['all_day'],
        $data['recurrence'],
        $data['recurrence_until'],
        $data['recurrence_count']
      ) ? $stats['updated']++ : $stats['errors']++;
    }
    elseif(recurs_beyond($row, $now)) {
      \store\end_appointment_recurrence($row['id'], $now)
        ? $stats['ended']++ : $stats['errors']++;
    }
    elseif($row['ends_at'] < $now || $row['recurrence_count']) {
      $stats['kept']++;
    }
    else {
      \store\delete_appointment($row['id'])
        ? $stats['deleted']++ : $stats['errors']++;
    }
  }

  foreach($upstream as $uid => $data) {
    if(isset($seen[$uid])) continue;

    \store\create_subscription_appointment(
      $uid,
      $id,
      $data['title'],
      $data['content'],
      $data['starts_at'],
      $data['ends_at'],
      $data['location'],
      $data['meeting'],
      $data['all_day'],
      $data['recurrence'],
      $data['recurrence_until'],
      $data['recurrence_count']
    ) ? $stats['inserted']++ : $stats['errors']++;
  }

  return $stats;
}

function normalize_feed_event($event) {
  [$recurrence, $until, $count] = translate_rrule(
    $event['rrule'], $event['starts_at'], $event['uid']);

  // Timed events spanning more than two full days (multi-day vacations
  // and the like) would fill the timed grid for their entire span;
  // treat them as all-day.
  $all_day = $event['all_day']
    || $event['ends_at'] - $event['starts_at'] > 2 * 86400;

  return [
    'title' => $event['summary'],
    'content' => $event['description'],
    'location' => $event['location'],
    'meeting' => extract_meeting_link($event),
    'starts_at' => gmdate('Y-m-d\TH:i:sP', $event['starts_at']),
    'ends_at' => gmdate('Y-m-d\TH:i:sP', $event['ends_at']),
    'all_day' => $all_day,
    'recurrence' => $recurrence,
    'recurrence_until' => $until,
    'recurrence_count' => $count,
  ];
}

function appointment_differs($row, $data) {
  return $row['title'] != $data['title']
    || $row['content'] != $data['content']
    || $row['location'] != $data['location']
    || $row['meeting'] != $data['meeting']
    || $row['starts_at'] != $data['starts_at']
    || $row['ends_at'] != $data['ends_at']
    || (bool)$row['all_day'] != $data['all_day']
    || $row['recurrence'] != $data['recurrence']
    || $row['recurrence_until'] != $data['recurrence_until']
    || $row['recurrence_count'] != $data['recurrence_count'];
}

function recurs_beyond($row, $moment) {
  return $row['recurrence']
    && !$row['recurrence_count']
    && (!$row['recurrence_until'] || $row['recurrence_until'] > $moment);
}

// Returns [recurrence, recurrence_until, recurrence_count]. When the RRULE
// can't be cleanly expressed in cron/int, we emit a warning and return
// all-nulls — the caller will store the first occurrence only.
function translate_rrule($rrule, $starts_unix, $uid) {
  if(!$rrule) return [null, null, null];

  $rule = [];
  foreach(explode(";", $rrule) as $pair) {
    if(!str_contains($pair, "=")) continue;
    [$key, $value] = explode("=", $pair, 2);
    $rule[strtoupper($key)] = $value;
  }

  $freq     = strtoupper($rule['FREQ'] ?? "");
  $interval = max(1, (int)($rule['INTERVAL'] ?? 1));
  $count    = isset($rule['COUNT']) ? (int)$rule['COUNT'] : null;
  $until    = isset($rule['UNTIL']) ? rrule_until_to_iso($rule['UNTIL']) : null;

  // UNTIL and COUNT are mutually exclusive per RFC and per our schema CHECK.
  // If a feed somehow ships both, prefer UNTIL.
  if($until !== null) $count = null;

  if(isset($rule['BYSETPOS'])) {
    \logger\warn("sync: dropping RRULE for $uid: BYSETPOS not supported");
    return [null, null, null];
  }

  // Local time, so the cron matches what the user sees in their calendar.
  $timezone = new \DateTimeZone(TIMEZONE);
  $local = (new \DateTime("@$starts_unix"))->setTimezone($timezone);
  $mm    = (int)$local->format('i');
  $hh    = (int)$local->format('H');
  $dom   = (int)$local->format('j');
  $month = (int)$local->format('n');
  $dow   = (int)$local->format('w'); // 0 (Sun) .. 6 (Sat)

  switch($freq) {
    case 'DAILY':
      return [(string)$interval, $until, $count];

    case 'WEEKLY':
      if($interval > 1) {
        \logger\warn("sync: dropping RRULE for $uid: WEEKLY INTERVAL>1 not expressible in cron");
        return [null, null, null];
      }
      $days = isset($rule['BYDAY'])
        ? byday_to_cron_days($rule['BYDAY'], $uid)
        : (string)$dow;
      if($days === null) return [null, null, null];
      return ["$mm $hh * * $days", $until, $count];

    case 'MONTHLY':
      if($interval > 1) {
        \logger\warn("sync: dropping RRULE for $uid: MONTHLY INTERVAL>1 not expressible in cron");
        return [null, null, null];
      }
      if(isset($rule['BYDAY'])) {
        // 'First Friday of the month' and friends need BYSETPOS semantics.
        \logger\warn("sync: dropping RRULE for $uid: MONTHLY BYDAY not supported");
        return [null, null, null];
      }
      $day = isset($rule['BYMONTHDAY']) ? (int)$rule['BYMONTHDAY'] : $dom;
      return ["$mm $hh $day * *", $until, $count];

    case 'YEARLY':
      if($interval > 1) {
        \logger\warn("sync: dropping RRULE for $uid: YEARLY INTERVAL>1 not expressible in cron");
        return [null, null, null];
      }
      $day = isset($rule['BYMONTHDAY']) ? (int)$rule['BYMONTHDAY'] : $dom;
      $mon = isset($rule['BYMONTH'])    ? (int)$rule['BYMONTH']    : $month;
      return ["$mm $hh $day $mon *", $until, $count];

    default:
      \logger\warn("sync: dropping RRULE for $uid: FREQ='$freq' not supported");
      return [null, null, null];
  }
}

function byday_to_cron_days($byday, $uid) {
  $map = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];

  $days = [];
  foreach(explode(",", $byday) as $day) {
    $day = strtoupper(trim($day));
    if(preg_match('/^[+-]?\d/', $day)) {
      // '2MO' = second Monday — BYSETPOS-flavored, not cron-expressible.
      \logger\warn("sync: dropping RRULE for $uid: BYDAY with numeric prefix ($day) not supported");
      return null;
    }
    if(!isset($map[$day])) {
      \logger\warn("sync: dropping RRULE for $uid: unknown BYDAY value '$day'");
      return null;
    }
    $days[] = $map[$day];
  }

  sort($days);
  return implode(",", $days);
}

function rrule_until_to_iso($value) {
  if(strlen($value) == 8 && ctype_digit($value)) {
    $datetime = \DateTime::createFromFormat('!Ymd', $value, new \DateTimeZone("UTC"));
    return $datetime ? $datetime->format('Y-m-d\TH:i:sP') : null;
  }

  if(str_ends_with($value, "Z")) {
    $datetime = \DateTime::createFromFormat('Ymd\THis\Z', $value, new \DateTimeZone("UTC"));
    return $datetime ? $datetime->format('Y-m-d\TH:i:sP') : null;
  }

  // Floating local time — assume app timezone.
  $timezone = new \DateTimeZone(TIMEZONE);
  $datetime = \DateTime::createFromFormat('Ymd\THis', $value, $timezone);
  if(!$datetime) return null;

  return $datetime->setTimezone(new \DateTimeZone("UTC"))->format('Y-m-d\TH:i:sP');
}

function extract_meeting_link($event) {
  if($event['conference']) return $event['conference'];

  $haystack = $event['location'] . "\n" . $event['description'];
  $patterns = [
    '~https?://(?:[a-z0-9-]+\.)*zoom\.us/[^\s<>"]+~i',
    '~https?://meet\.google\.com/[^\s<>"]+~i',
    '~https?://teams\.microsoft\.com/[^\s<>"]+~i',
    '~https?://teams\.live\.com/[^\s<>"]+~i',
  ];

  foreach($patterns as $pattern)
    if(preg_match($pattern, $haystack, $match)) return $match[0];

  return null;
}
