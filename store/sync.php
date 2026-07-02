<?php
// iCal subscription sync.
//
// Pulls each subscription's iCal feed, mirrors its events into appointments,
// and translates RRULEs into the native (int|cron) recurrence column plus the
// recurrence_until / recurrence_count siblings.
//
// Sync rules:
//   1. Past appointments (ends_at < now) missing from the feed are left alone
//      (historical record).
//   2. Future appointments (ends_at >= now) missing from the feed are deleted.
//   3. Appointments still in the feed get their mirrored fields refreshed
//      (title, content, location, meeting, starts_at, ends_at, all_day, plus
//      the recurrence trio).
//   4. `going` and `color` are user annotations and are never touched.
//   5. New feed entries are inserted.
//
// RRULE handling: we don't expand into one row per occurrence. The frontend
// renders recurrences from the cron/int field. RRULEs that can't be expressed
// in cron (BYSETPOS, biweekly, BYDAY-with-prefix, etc.) get dropped — the
// first instance is imported as a single appointment with no recurrence and a
// warning is logged. UNTIL/COUNT map to the recurrence_until/recurrence_count
// columns (mutually exclusive per schema CHECK).

namespace store;

require_once __DIR__ . "/../vendor/ical.php";

function sync_all_subscriptions() {
  $results = [];
  foreach(list_subscriptions() ?: [] as $sub) {
    $results[$sub['id']] = sync_subscription($sub['id']);
  }
  return $results;
}

function sync_subscription($id) {
  $sub = get_subscription($id);
  if(!$sub) return ['error' => 'subscription not found'];

  $response = \http\get($sub['url']);
  if($response['state'] != 'success' || $response['status'] >= 400) {
    \logger\warn("sync: fetch failed for subscription $id: status {$response['status']}");
    return ['error' => "fetch failed: {$response['status']}"];
  }

  try {
    $ical = new \ICal\ICal();
    $ical->skipRecurrence = true; // we store recurrence patterns, not expanded rows
    $ical->defaultTimeZone = getenv('TIMEZONE') ?: 'Europe/Amsterdam';
    $ical->initString($response['body']);
  } catch(\Exception $e) {
    \logger\warn("sync: parse failed for subscription $id: " . $e->getMessage());
    return ['error' => 'parse failed'];
  }

  $feed = [];
  foreach($ical->events() as $event) {
    if(!$event->uid) continue;
    $normalized = normalize_feed_event($event, $sub);
    if($normalized === null) continue;
    $feed[$event->uid] = $normalized;
  }

  $existing = all(
    'SELECT `id`, `ends_at` FROM `appointments` WHERE `subscription_id` = ?',
    [$id]
  ) ?: [];

  $now_iso = gmdate('Y-m-d\TH:i:sP');
  $stats = ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'kept_past' => 0, 'errors' => 0];
  $existing_ids = [];

  foreach($existing as $row) {
    $existing_ids[$row['id']] = true;

    if(isset($feed[$row['id']])) {
      mirror_subscription_appointment($row['id'], $feed[$row['id']])
        ? $stats['updated']++
        : $stats['errors']++;
    } elseif($row['ends_at'] < $now_iso) {
      $stats['kept_past']++;
    } else {
      delete_appointment($row['id']);
      $stats['deleted']++;
    }
  }

  foreach($feed as $uid => $data) {
    if(isset($existing_ids[$uid])) continue;
    insert_subscription_appointment($id, $uid, $data)
      ? $stats['inserted']++
      : $stats['errors']++;
  }

  return $stats;
}

function normalize_feed_event($event, $sub) {
  $dtstart_arr = $event->dtstart_array ?? null;
  $dtend_arr   = $event->dtend_array ?? null;

  if(!$dtstart_arr || !isset($dtstart_arr[2])) return null;

  $starts_unix = (int)$dtstart_arr[2];
  $ends_unix   = isset($dtend_arr[2]) ? (int)$dtend_arr[2] : $starts_unix;
  if($ends_unix < $starts_unix) $ends_unix = $starts_unix;

  $all_day = ($dtstart_arr[0]['VALUE'] ?? '') === 'DATE';

  [$recurrence, $until, $count] = translate_rrule(
    $event->rrule ?? '',
    $starts_unix,
    $event->uid
  );

  return [
    'title'            => $event->summary ?? '',
    'content'          => $event->description ?? '',
    'location'         => $event->location ?? '',
    'meeting'          => extract_meeting_link($event),
    'starts_at'        => gmdate('Y-m-d\TH:i:sP', $starts_unix),
    'ends_at'          => gmdate('Y-m-d\TH:i:sP', $ends_unix),
    'all_day'          => $all_day,
    'recurrence'       => $recurrence,
    'recurrence_until' => $until,
    'recurrence_count' => $count,
    'color'            => $sub['color'],
  ];
}

// Returns [recurrence, recurrence_until, recurrence_count].
// When the RRULE can't be cleanly expressed in cron/int, we emit a warning and
// return all-nulls — the caller will store the first occurrence only.
function translate_rrule($rrule, $starts_unix, $uid) {
  if(!$rrule) return [null, null, null];

  $rule = [];
  foreach(explode(';', $rrule) as $pair) {
    if(!str_contains($pair, '=')) continue;
    [$k, $v] = explode('=', $pair, 2);
    $rule[strtoupper($k)] = $v;
  }

  $freq     = strtoupper($rule['FREQ'] ?? '');
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

  // Local time so the cron matches what the user sees in their calendar.
  $tz    = new \DateTimeZone(getenv('TIMEZONE') ?: 'Europe/Amsterdam');
  $local = (new \DateTime("@$starts_unix"))->setTimezone($tz);
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
        // 'first Friday of month' and friends need BYSETPOS semantics.
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

function byday_to_cron_days($byday_value, $uid) {
  $map = ['SU'=>0,'MO'=>1,'TU'=>2,'WE'=>3,'TH'=>4,'FR'=>5,'SA'=>6];

  $parts = [];
  foreach(explode(',', $byday_value) as $day) {
    $day = strtoupper(trim($day));
    if(preg_match('/^[+-]?\d+/', $day)) {
      // '2MO' = second Monday — BYSETPOS-flavored, not cron-expressible.
      \logger\warn("sync: dropping RRULE for $uid: BYDAY with numeric prefix ($day) not supported");
      return null;
    }
    if(!isset($map[$day])) {
      \logger\warn("sync: dropping RRULE for $uid: unknown BYDAY value '$day'");
      return null;
    }
    $parts[] = $map[$day];
  }
  sort($parts);
  return implode(',', $parts);
}

function rrule_until_to_iso($value) {
  if(strlen($value) === 8 && ctype_digit($value)) {
    $dt = \DateTime::createFromFormat('!Ymd', $value, new \DateTimeZone('UTC'));
    return $dt ? $dt->format('Y-m-d\TH:i:sP') : null;
  }
  if(str_ends_with($value, 'Z')) {
    $dt = \DateTime::createFromFormat('Ymd\THis\Z', $value, new \DateTimeZone('UTC'));
    return $dt ? $dt->format('Y-m-d\TH:i:sP') : null;
  }
  // Floating local time — assume app timezone.
  $tz = new \DateTimeZone(getenv('TIMEZONE') ?: 'Europe/Amsterdam');
  $dt = \DateTime::createFromFormat('Ymd\THis', $value, $tz);
  if($dt === false) return null;
  $dt->setTimezone(new \DateTimeZone('UTC'));
  return $dt->format('Y-m-d\TH:i:sP');
}

function extract_meeting_link($event) {
  $text = ($event->location ?? '') . "\n" . ($event->description ?? '');
  $patterns = [
    '~https?://(?:[a-z0-9-]+\.)*zoom\.us/[^\s<>"]+~i',
    '~https?://meet\.google\.com/[^\s<>"]+~i',
    '~https?://teams\.microsoft\.com/[^\s<>"]+~i',
    '~https?://teams\.live\.com/[^\s<>"]+~i',
  ];
  foreach($patterns as $p) {
    if(preg_match($p, $text, $m)) return $m[0];
  }
  return null;
}

function mirror_subscription_appointment($id, $data) {
  return exec_query('UPDATE `appointments` SET
    `title` = ?,
    `content` = ?,
    `location` = ?,
    `meeting` = ?,
    `starts_at` = ?,
    `ends_at` = ?,
    `all_day` = ?,
    `recurrence` = ?,
    `recurrence_until` = ?,
    `recurrence_count` = ?
  WHERE `id` = ?', [
    $data['title'],
    $data['content'],
    $data['location'],
    $data['meeting'],
    $data['starts_at'],
    $data['ends_at'],
    $data['all_day'] ? 1 : 0,
    $data['recurrence'],
    $data['recurrence_until'],
    $data['recurrence_count'],
    $id
  ]);
}

function insert_subscription_appointment($sub_id, $uid, $data) {
  return exec_query('INSERT INTO `appointments` (
    `id`, `calendar_id`, `subscription_id`,
    `title`, `content`, `location`, `meeting`,
    `recurrence`, `recurrence_until`, `recurrence_count`,
    `all_day`, `going`, `circled`, `color`,
    `starts_at`, `ends_at`
  ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    $uid,
    $sub_id,
    $data['title'],
    $data['content'],
    $data['location'],
    $data['meeting'],
    $data['recurrence'],
    $data['recurrence_until'],
    $data['recurrence_count'],
    $data['all_day'] ? 1 : 0,
    1, // going (default true per schema; user-managed thereafter)
    0, // circled (user-managed thereafter)
    $data['color'],
    $data['starts_at'],
    $data['ends_at']
  ]);
}
