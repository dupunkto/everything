<?php
// iCalendar subscription syncer.

if($method != 'POST') fail("Method not allowed.", status: 405);

// The syncer is idempotent. It optionally takes a specific subscription to sync,
// and otherwise syncs all available subscriptions.

$subscriptions = [];

if(isset($_GET['id'])) {
  $subscriptions[] = \store\get_subscription($_GET['id'])
    or fail("Subscription not found.", status: 404);
}

if($subscriptions == []) {
  $subscriptions = \store\list_subscriptions();
}

// This syncers mirrors appointments from an external iCalendar feed into the
// 'appointments' table in the store. It mirros the title, content, location,
// meeting, starts_at, ends_at, all_day and recurrence columns. The going,
// urgent, and color columns are user annotations that are never overwritten.
// Appointments no longer in the feed are deleted unless the subscription is
// configured to retain history and the appointment is past or recurring.

foreach($subscriptions as $subscription) {
  $response = \http\get($subscription['url']);

  if($response['state'] != 'success') {
    fail("Fetching {$subscription['url']} failed: network error");
  }

  if($response['status'] >= 400) {
    fail("Fetching {$subscription['url']} failed: HTTP {$response['status']}");
  }

  $feed = \icalendar\parse_feed($response['body'])
    or fail("Parsing {$subscription['url']} failed: not an iCalendar feed.");

  $upstream = [];
  $slots = [];
  $duplicates = [];

  foreach($feed['events'] as $event) {
    // Skip recurrence exceptions, since we do not support them, and cancelled events,
    // because we do not display those either.
    if($event['is_exception'] || $event['status'] == 'CANCELLED') continue;

    if(cast_bool($subscription['deduplicate'])) {
      $slot = $event['starts_at'] . ':' . ($event['ends_at'] - $event['starts_at']);
      if(isset($slots[$slot])) {
        $duplicates[$event['uid']] = true;
        continue;
      }
      $slots[$slot] = true;
    }

    $upstream[$event['uid']] = normalize_feed_event($event);
  }

  $existing = \store\list_appointments_by_subscription($subscription['id']);

  $now = gmdate('Y-m-d\TH:i:sP');
  $stats = ['inserted' => 0, 'updated' => 0, 'deleted' => 0, 'kept' => 0];
  $seen = [];

  foreach($existing as $row) {
    $seen[$row['id']] = true;

    if(isset($upstream[$row['id']])) {
      $data = $upstream[$row['id']];

      if($row['title'] == $data['title']
        && $row['content'] == $data['content']
        && $row['location'] == $data['location']
        && $row['meeting'] == $data['meeting']
        && $row['starts_at'] == $data['starts_at']
        && $row['ends_at'] == $data['ends_at']
        && cast_bool($row['all_day']) == $data['all_day']
        && $row['recurrence'] == $data['recurrence']) continue;

      $fields = \core\diff($row, ...$data);
      \store\update_appointment_body(
        $row['id'],
        $data['title'],
        $data['content'],
        $data['starts_at'],
        $data['ends_at'],
        $data['location'],
        $data['meeting'],
        $data['all_day'],
        $data['recurrence']
      );
      \caldav\mark_resource_changed('appointment', $row['id']);
      \store\put_audit_log('appointments', $row['id'], "Updated [" . join(", ", $fields) . "] for appointments/{$row['id']}.", 'syncer');
      $stats['updated']++;
    }
    elseif(!isset($duplicates[$row['id']])
      && cast_bool($subscription['history'])
      && ($row['ends_at'] < $now || $row['recurrence'])) {
      $stats['kept']++;
    }
    else {
      \store\delete_appointment($row['id']);
      \caldav\mark_resource_deleted('appointment', $row['id']);
      \store\put_audit_log('appointments', $row['id'], "Deleted appointments/{$row['id']}.", 'syncer', operation: 'delete');
      $stats['deleted']++;
    }
  }

  foreach($upstream as $uid => $data) {
    if(isset($seen[$uid])) continue;

    \store\put_subscription_appointment(
      $uid,
      $subscription['id'],
      $data['title'],
      $data['content'],
      $data['starts_at'],
      $data['ends_at'],
      $data['location'],
      $data['meeting'],
      $data['all_day'],
      $data['recurrence']
    );
    \store\put_audit_log('appointments', $uid, "Created appointments/$uid.", 'syncer', operation: 'insert');
    $stats['inserted']++;
  }

  \logger\info("Subscription synced.", [
    'subscription_id' => $subscription['id'],
    'url' => $subscription['url'],
    'stats' => $stats,
  ]);
}

function normalize_feed_event($event) {
  $recurrence = null;

  $starts_at = (new \DateTimeImmutable("@{$event['starts_at']}"))->
    setTimezone(new \DateTimeZone(TIMEZONE));

  if ($event['rrule']) {
    if (\recurrence\valid($event['rrule'], $starts_at)) {
      $recurrence = $event['rrule'];
    } else {
      \logger\warn("sync: dropping invalid RRULE for {$event['uid']}");
    }
  }

  // Timed events that span more than two full days are probably
  // either entered incorrectly by the user, or serialized incorrectly.
  // Treat them as all-day events.
  $all_day = $event['all_day']
    || $event['ends_at'] - $event['starts_at'] > 2 * 86400;

  return [
    'title' => $event['summary'],
    'content' => $event['description'],
    'location' => $event['location'],
    'meeting' => $event['conference'] ?: extract_meeting($event),
    'starts_at' => gmdate('Y-m-d\TH:i:sP', $event['starts_at']),
    'ends_at' => gmdate('Y-m-d\TH:i:sP', $event['ends_at']),
    'all_day' => $all_day,
    'recurrence' => $recurrence,
  ];
}

function extract_meeting($event) {
  $haystack = $event['location'] . "\n" . $event['description'];
  $patterns = [
    '~https?://(?:[a-z0-9-]+\.)*zoom\.us/[^\s<>"]+~i',
    '~https?://meet\.google\.com/[^\s<>"]+~i',
    '~https?://teams\.microsoft\.com/[^\s<>"]+~i',
    '~https?://teams\.live\.com/[^\s<>"]+~i',
  ];

  foreach($patterns as $pattern) {
    if(preg_match($pattern, $haystack, $match)) return $match[0];
  }

  return null;
}

http_response_code(204);
