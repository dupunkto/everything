<?php

namespace shares;

function sources() {
  return array_map(fn($source) => [
    'key' => $source['type'] . ':' . $source['id'],
    'type' => $source['type'],
    'id' => $source['id'],
    'label' => $source['title'] . ($source['subtitle'] ? " ({$source['subtitle']})" : ""),
  ], \store\list_sources());
}

function header($name) {
  $body = "BEGIN:VCALENDAR\r\n";
  $body .= \caldav\line('PRODID', '-//Everything//Shared feed//EN');
  $body .= \caldav\line('VERSION', '2.0');
  $body .= \caldav\line('CALSCALE', 'GREGORIAN');
  $body .= \caldav\line('X-WR-CALNAME', \icalendar\escape_text($name));
  $body .= \caldav\line('X-WR-TIMEZONE', TIMEZONE);
  return $body;
}

function property_lines($row, $redacted) {
  $body = "";
  $known = [
    'UID', 'DTSTAMP', 'SEQUENCE', 'CREATED', 'LAST-MODIFIED', 'SUMMARY',
    'DESCRIPTION', 'PRIORITY', 'DTSTART', 'DTEND', 'DURATION', 'LOCATION',
    'URL', 'CONFERENCE', 'RRULE', ...CALDAV_PRIVATE_PROPERTIES,
  ];

  foreach(\store\list_properties('appointment', $row['id']) as $property) {
    if(in_array($property['name'], $known)) continue;
    if($redacted && $property['name'] != 'TRANSP') continue;
    $params = json_decode($property['parameters'], true) ?: [];
    $body .= \caldav\line($property['name'], $property['value'], $params);
  }

  return $body;
}

function appointment($row, $redacted, $title = "Event", $travel = null) {
  $is_travel = $travel !== null;
  $dates = \store\get_log_dates('appointments', $row['id']);
  $modified = $dates['modified_at'] ?: $dates['created_at'] ?: $row['starts_at'];
  $suffix = $is_travel ? "-travel-$travel" : "";

  $body = "BEGIN:VEVENT\r\n";
  $body .= \caldav\line('UID', \icalendar\escape_text($row['id'] . $suffix . '@' . HOST));
  $body .= \caldav\line('DTSTAMP', \caldav\utc($modified));
  $body .= \caldav\line('SUMMARY', $is_travel || $redacted
    ? ($is_travel ? "Travel time" : (CALENDAR_REDACTED_TITLES ? \icalendar\escape_text($title) : "Event"))
    : \icalendar\escape_text($row['title']));

  if($is_travel) {
    if($travel == 'before') {
      $ends = new \DateTimeImmutable($row['starts_at']);
      $starts = $ends->modify('-' . (int)$row['travel_before'] . ' minutes');
    }
    else {
      $starts = new \DateTimeImmutable($row['ends_at']);
      $ends = $starts->modify('+' . (int)$row['travel_after'] . ' minutes');
    }
  }
  else {
    $starts = new \DateTimeImmutable($row['starts_at']);
    $ends = new \DateTimeImmutable($row['ends_at']);
  }

  if(!$is_travel && \cast_bool($row['all_day'])) {
    $date = [['name' => 'VALUE', 'values' => ['DATE']]];
    $body .= \caldav\line('DTSTART', \caldav\local_day($row['starts_at']), $date);
    $body .= \caldav\line('DTEND', \caldav\local_day($row['ends_at']), $date);
  }
  elseif($row['recurrence']) {
    $timezone = [['name' => 'TZID', 'values' => [TIMEZONE]]];
    $body .= \caldav\line('DTSTART', \caldav\local_time($starts->format('c')), $timezone);
    $body .= \caldav\line('DTEND', \caldav\local_time($ends->format('c')), $timezone);
  }
  else {
    $body .= \caldav\line('DTSTART', \caldav\utc($starts->format('c')));
    $body .= \caldav\line('DTEND', \caldav\utc($ends->format('c')));
  }

  if($row['recurrence']) $body .= \caldav\line('RRULE', $row['recurrence']);

  if(!$is_travel && !$redacted) {
    if($row['content'] !== null && $row['content'] !== "")
      $body .= \caldav\line('DESCRIPTION', \icalendar\escape_text($row['content']));
    if($row['location'])
      $body .= \caldav\line('LOCATION', \icalendar\escape_text($row['location']));
    if($row['meeting']) {
      $body .= \caldav\line('URL', $row['meeting']);
      $body .= \caldav\line('CONFERENCE', $row['meeting'], [['name' => 'VALUE', 'values' => ['URI']]]);
    }
    $body .= \caldav\line('PRIORITY', (string)\caldav\priority('appointment', $row['id'], \cast_bool($row['urgent'])));
  }

  if(!$is_travel) $body .= property_lines($row, $redacted);
  return $body . "END:VEVENT\r\n";
}

function deadline($task, $redacted) {
  $starts = new \DateTimeImmutable($task['next']);
  $all_day = \cast_bool(@$task['due_all_day']);
  $ends = $all_day ? $starts->setTimezone(new \DateTimeZone(TIMEZONE))->modify('+1 day') : $starts;

  $body = "BEGIN:VEVENT\r\n";
  $body .= \caldav\line('UID', \icalendar\escape_text("deadline-{$task['id']}@" . HOST));
  $body .= \caldav\line('DTSTAMP', \caldav\utc($task['updated_date'] ?: $task['open_at']));
  $body .= \caldav\line('SUMMARY', $redacted ? "Event" : \icalendar\escape_text($task['title']));
  if(!$redacted && $task['content'] !== null && $task['content'] !== "")
    $body .= \caldav\line('DESCRIPTION', \icalendar\escape_text($task['content']));
  if($all_day) {
    $date = [['name' => 'VALUE', 'values' => ['DATE']]];
    $body .= \caldav\line('DTSTART', \caldav\local_day($starts->format('c')), $date);
    $body .= \caldav\line('DTEND', \caldav\local_day($ends->format('c')), $date);
  }
  else {
    $body .= \caldav\line('DTSTART', \caldav\utc($starts->format('c')));
    $body .= \caldav\line('DTEND', \caldav\utc($ends->format('c')));
  }
  return $body . "END:VEVENT\r\n";
}

function birthday($contact, $redacted) {
  $start = \DateTimeImmutable::createFromFormat('!Y-n-j', "2000-{$contact['birth_month']}-{$contact['birth_day']}");
  if(!$start) return "";
  $end = $start->modify('+1 day');
  $date = [['name' => 'VALUE', 'values' => ['DATE']]];
  $title = \contacts\contact_display_name($contact);

  $body = "BEGIN:VEVENT\r\n";
  $body .= \caldav\line('UID', "birthday-{$contact['id']}@" . HOST);
  $body .= \caldav\line('DTSTAMP', \caldav\utc("now"));
  $body .= \caldav\line('SUMMARY', $redacted ? "Event" : \icalendar\escape_text($title));
  $body .= \caldav\line('DTSTART', $start->format('Ymd'), $date);
  $body .= \caldav\line('DTEND', $end->format('Ymd'), $date);
  $body .= \caldav\line('RRULE', 'FREQ=YEARLY');
  return $body . "END:VEVENT\r\n";
}

function serialize($share) {
  $body = header($share['name']);

  foreach(\store\list_share_sources($share['id']) as $selected) {
    $redacted = $selected['mode'] == 'redacted';

    if($selected['calendar_id']) {
      $appointments = \store\list_appointments_by_calendar($selected['calendar_id']);
    }
    else {
      $subscription = \store\get_subscription($selected['subscription_id']);
      if(!$subscription) continue;
      $appointments = array_filter(
        \store\list_appointments_by_subscription($subscription['id']),
        fn($row) => !$subscription['filter']
          || stripos($row['title'], $subscription['filter']) !== false
      );
    }

    foreach($appointments as $row) {
      if(!\cast_bool($row['going'])) continue;
      $body .= appointment($row, $redacted, title: $selected['title']);
      if(!\cast_bool($row['all_day']) && (int)$row['travel_before'] > 0)
        $body .= appointment($row, $redacted, title: $selected['title'], travel: 'before');
      if(!\cast_bool($row['all_day']) && (int)$row['travel_after'] > 0)
        $body .= appointment($row, $redacted, title: $selected['title'], travel: 'after');
    }
  }

  if(\cast_bool($share['deadlines'])) {
    foreach(\store\list_tasks("not:nvm", [], respect_horizon: false) as $task)
      if($task['next']) $body .= deadline($task, false);
  }

  if(\cast_bool($share['birthdays'])) {
    foreach(\store\list_contacts() as $contact)
      if($contact['birth_day'] && $contact['birth_month']) $body .= birthday($contact, false);
  }

  return $body . "END:VCALENDAR\r\n";
}
