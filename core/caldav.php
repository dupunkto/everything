<?php
// CalDAV helpers.
// Lovingly written by Claude.

namespace caldav;

use Sabre\VObject\Component;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

define('CALDAV_PRINCIPAL', 'everything');
define('CALDAV_VIRTUAL_COLLECTIONS', ['reminders', 'backlog', 'blocked', 'wishlist', 'travel']);

function collections() {
  $collections = [];
  foreach(\store\list_calendars() as $calendar) {
    $collections[$calendar['id']] = [
      'id' => $calendar['id'],
      'type' => 'appointment',
      'component' => 'VEVENT',
      'title' => $calendar['title'],
      'displayname' => $calendar['subtitle']
        ? "{$calendar['title']} (" . mb_strtolower($calendar['subtitle']) . ")"
        : $calendar['title'],
      'color' => $calendar['color'],
      'position' => $calendar['position'],
      'calendar' => $calendar,
      'subscription' => null,
      'readonly' => false,
    ];
  }

  foreach(\store\list_subscriptions() as $subscription) {
    $collections[$subscription['id']] = [
      'id' => $subscription['id'],
      'type' => 'appointment',
      'component' => 'VEVENT',
      'title' => $subscription['title'],
      'displayname' => $subscription['subtitle']
        ? "{$subscription['title']} (" . mb_strtolower($subscription['subtitle']) . ")"
        : $subscription['title'],
      'color' => $subscription['color'],
      'position' => $subscription['position'],
      'calendar' => null,
      'subscription' => $subscription,
      'readonly' => true,
    ];
  }

  $position = $collections
    ? max(array_column($collections, 'position')) + 1
    : 0;
  $virtual = [
    'reminders' => "Reminders",
    'blocked' => "Blocked",
    'backlog' => "Backlog",
    'wishlist' => "Wishlist",
    'travel' => "Travel time",
  ];
  foreach($virtual as $id => $title) {
    $collections[$id] = [
      'id' => $id,
      'type' => match($id) {
        'wishlist' => 'wish',
        'travel' => 'travel',
        default => 'task',
      },
      'component' => $id == 'travel' ? 'VEVENT' : 'VTODO',
      'title' => $title,
      'displayname' => $title,
      'color' => $id == 'travel' ? "#808080" : null,
      'position' => $position++,
      'calendar' => null,
      'subscription' => null,
      'readonly' => $id == 'travel',
    ];
  }

  return $collections;
}

function collection($id) {
  return @collections()[$id];
}

function parse_displayname($value) {
  $value = trim($value);
  if(!str_ends_with($value, ")")) return [$value, null];

  $depth = 0;
  for($i = strlen($value) - 1; $i >= 0; $i--) {
    if($value[$i] == ")") $depth++;
    elseif($value[$i] == "(" && --$depth == 0) {
      if($i == 0 || !ctype_space($value[$i - 1])) return [$value, null];
      return [rtrim(substr($value, 0, $i)), trim(substr($value, $i + 1, -1))];
    }
  }

  return [$value, null];
}

function task_collection($status) {
  return match($status) {
    'todo', 'wip', 'done' => 'reminders',
    'backlog' => 'backlog',
    'blocked' => 'blocked',
    default => null,
  };
}

function task_status($collection, $incoming, $current) {
  $projected = $current == 'done' ? 'COMPLETED' : 'NEEDS-ACTION';
  if($incoming == $projected) {
    return $collection == task_collection($current) ? $current : match($collection) {
      'backlog' => 'backlog',
      'blocked' => 'blocked',
      default => 'todo',
    };
  }

  if($incoming == 'COMPLETED') return 'done';
  if($projected == 'COMPLETED') return 'todo';

  return match($collection) {
    'backlog' => 'backlog',
    'blocked' => 'blocked',
    default => 'todo',
  };
}

function reconcile() {
  \store\transaction(function() {
    $existing = [];
    foreach(\store\list_caldav_resources() as $resource)
      $existing[$resource['entity_type'] . ':' . $resource['entity_id']] = $resource;

    $seen = [];
    $changes = [];

    $visit = function($type, $row, $desired, $default_href = null, $uid = null) use (&$existing, &$seen, &$changes) {
      $key = "$type:{$row['id']}";
      $seen[$key] = true;
      $resource = @$existing[$key];
      if(!$resource && !$desired) return;
      $href = @$resource['href'] ?: $default_href ?: $row['id'] . ".ics";
      $old_href = @$resource['href'];
      $current = @$resource['collection'];
      $occupied = $desired ? \store\get_caldav_resource_by_href($desired, $href) : null;
      if($occupied && ($occupied['entity_type'] != $type || $occupied['entity_id'] != $row['id']))
        $href = $row['id'] . "-$type.ics";

      if(!$resource) {
        \store\update_caldav_resource($type, $row['id'], $href, $desired, uid: $uid);
        if($desired) $changes[] = ['collection' => $desired, 'href' => $href, 'operation' => 'upsert'];
      }
      elseif($current != $desired) {
        if($current) $changes[] = ['collection' => $current, 'href' => $old_href, 'operation' => 'delete'];
        if($desired) $changes[] = ['collection' => $desired, 'href' => $href, 'operation' => 'upsert'];
        \store\update_caldav_resource($type, $row['id'], $href, $desired, uid: $uid);
      }
    };

    $appointments = [
      ...\store\list_calendar_appointments(),
      ...\store\list_subscription_appointments(),
    ];
    foreach($appointments as $row) {
      $going = \cast_boolean($row['going']);
      $filtered = $row['subscription_id'] && $row['subscription_filter']
        && stripos($row['title'], $row['subscription_filter']) === false;
      $visible = $going && !$filtered;
      $collection = $row['calendar_id'] ?: $row['subscription_id'];
      $visit('appointment', $row, $visible ? $collection : null);
      $travel = $visible && !\cast_boolean($row['all_day']);
      $before = $travel && (int)$row['travel_before'] > 0 ? 'travel' : null;
      $after = $travel && (int)$row['travel_after'] > 0 ? 'travel' : null;
      $visit('travel_before', $row, $before,
        $row['id'] . "-travel-before.ics", $row['id'] . "-travel-before");
      $visit('travel_after', $row, $after,
        $row['id'] . "-travel-after.ics", $row['id'] . "-travel-after");
    }

    foreach(\store\list_tasks("", [], respect_horizon: false) as $row) {
      $expired = $row['expire_at'] && strtotime($row['expire_at']) <= time();
      $visit('task', $row, $expired ? null : task_collection($row['status']));
    }

    foreach(\store\list_wishes() as $row)
      $visit('wish', $row, $row['status'] == 'nvm' ? null : 'wishlist');

    foreach($existing as $key => $resource) {
      if(isset($seen[$key])) continue;
      if($resource['collection']) $changes[] = [
        'collection' => $resource['collection'],
        'href' => $resource['href'],
        'operation' => 'delete',
      ];
      \store\delete_caldav_resource($resource['entity_type'], $resource['entity_id']);
    }

    if($changes) \store\put_caldav_changes($changes);
  });
}

function mark_resource_changed($type, $id) {
  $resource = \store\get_caldav_resource($type, $id);
  if(!$resource) return true;

  \store\touch_caldav_resource($type, $id);
  if($resource['collection'])
    \store\put_caldav_changes([[
      'collection' => $resource['collection'],
      'href' => $resource['href'],
      'operation' => 'upsert',
    ]]);
  if($type == 'appointment') mark_travel_changed($id);
  return true;
}

function mark_travel_changed($id) {
  foreach(['travel_before', 'travel_after'] as $type) {
    $resource = \store\get_caldav_resource($type, $id);
    if(!$resource || !$resource['collection']) continue;
    \store\touch_caldav_resource($type, $id);
    \store\put_caldav_changes([[
      'collection' => $resource['collection'],
      'href' => $resource['href'],
      'operation' => 'upsert',
    ]]);
  }
  return true;
}

function hide_resource($type, $id) {
  $resource = \store\get_caldav_resource($type, $id);
  if(!$resource) return true;

  \store\update_caldav_resource($type, $id, $resource['href'], null);
  \store\touch_caldav_resource($type, $id);
  if($resource['collection'])
    \store\put_caldav_changes([[
      'collection' => $resource['collection'],
      'href' => $resource['href'],
      'operation' => 'delete',
    ]]);
  return true;
}

function mark_resource_deleted($type, $id) {
  if($type == 'appointment') {
    mark_resource_deleted('travel_before', $id);
    mark_resource_deleted('travel_after', $id);
  }

  $resource = \store\get_caldav_resource($type, $id);
  if(!$resource) return true;

  \store\delete_caldav_resource($type, $id);
  if($resource['collection'])
    \store\put_caldav_changes([[
      'collection' => $resource['collection'],
      'href' => $resource['href'],
      'operation' => 'delete',
    ]]);
  return true;
}

function entity($resource) {
  return match($resource['entity_type']) {
    'appointment', 'travel_before', 'travel_after' => \store\get_appointment($resource['entity_id']),
    'task' => \store\get_task($resource['entity_id']),
    'wish' => \store\get_wish($resource['entity_id']),
  };
}

function parameter($name, $values) {
  $values = array_map(function($value) {
    if(preg_match('/[:;,\s]/', $value)) return '"' . addcslashes($value, '"\\') . '"';
    return $value;
  }, $values);
  return ";" . strtoupper($name) . "=" . implode(",", $values);
}

function fold($line) {
  $result = "";
  $first = true;
  while(strlen($line) > ($first ? 75 : 74)) {
    $limit = $first ? 75 : 74;
    while($limit > 0 && (ord($line[$limit]) & 0xc0) == 0x80) $limit--;
    $result .= substr($line, 0, $limit) . "\r\n ";
    $line = substr($line, $limit);
    $first = false;
  }
  return $result . $line . "\r\n";
}

function line($name, $value, $parameters = []) {
  $line = strtoupper($name);
  foreach($parameters as $param)
    $line .= parameter($param['name'], $param['values']);
  return fold($line . ":" . $value);
}

function utc($value) {
  return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone("UTC"))->format('Ymd\THis\Z');
}

function local_day($value) {
  return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone(TIMEZONE))->format('Ymd');
}

function local_time($value) {
  return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone(TIMEZONE))->format('Ymd\THis');
}

function duration($seconds) {
  $sign = $seconds < 0 ? "-" : "";
  $seconds = abs((int)$seconds);
  $days = intdiv($seconds, 86400);
  $seconds %= 86400;
  $hours = intdiv($seconds, 3600);
  $seconds %= 3600;
  $minutes = intdiv($seconds, 60);
  $seconds %= 60;
  $value = "P" . ($days ? $days . "D" : "");
  if($hours || $minutes || $seconds || !$days)
    $value .= "T" . ($hours ? $hours . "H" : "") . ($minutes ? $minutes . "M" : "")
      . ($seconds || (!$days && !$hours && !$minutes) ? $seconds . "S" : "");
  return $sign . $value;
}

function priority($type, $id, $urgent) {
  foreach(\store\list_properties($type, $id) as $property) {
    if($property['name'] != 'PRIORITY') continue;
    $value = (int)$property['value'];
    if(($urgent && $value > 0 && $value < 9) || (!$urgent && ($value == 0 || $value == 9))) return $value;
  }
  return $urgent ? 1 : 0;
}

function unknown_lines($type, $id, $skip = []) {
  $result = "";
  foreach(\store\list_properties($type, $id) as $property) {
    if(in_array($property['name'], $skip)) continue;
    $params = json_decode($property['parameters'], true) ?: [];
    $result .= line($property['name'], $property['value'], $params);
  }
  return $result;
}

function alarm_lines($type, $id) {
  $alarms = [];
  foreach(\store\list_alarms($type, $id) as $alarm) {
    $body = "BEGIN:VALARM\r\n";
    $body .= line('ACTION', 'DISPLAY');
    if($alarm['trigger_at']) $body .= line('TRIGGER', utc($alarm['trigger_at']), [
      ['name' => 'VALUE', 'values' => ['DATE-TIME']],
    ]);
    else {
      $params = $alarm['relative_to'] == 'end'
        ? [['name' => 'RELATED', 'values' => ['END']]] : [];
      $body .= line('TRIGGER', duration($alarm['trigger_offset']), $params);
    }
    $body .= line('DESCRIPTION', \icalendar\escape_text($alarm['description'] ?: "Reminder"));
    $body .= unknown_lines('alarm', $alarm['id'], ['ACTION', 'TRIGGER', 'DESCRIPTION']);
    $alarms[] = $body . "END:VALARM\r\n";
  }
  sort($alarms);
  return implode('', $alarms);
}

function serialize($resource) {
  $row = entity($resource);
  if(!$row) return null;

  $type = $resource['entity_type'];
  $is_travel = in_array($type, ['travel_before', 'travel_after']);
  $table = $type == 'appointment' || $is_travel ? 'appointments' : $type . 's';
  $dates = \store\get_log_dates($table, $row['id']);
  $component = $type == 'appointment' || $is_travel ? 'VEVENT' : 'VTODO';

  $body = "BEGIN:VCALENDAR\r\n";
  $body .= line('PRODID', '-//Everything//CalDAV//EN');
  $body .= line('VERSION', '2.0');
  $body .= line('CALSCALE', 'GREGORIAN');
  $body .= line('X-WR-TIMEZONE', TIMEZONE);
  $body .= "BEGIN:$component\r\n";
  $body .= line('UID', \icalendar\escape_text($resource['uid']));
  $body .= line('DTSTAMP', utc($resource['touched_at']));
  $body .= line('SEQUENCE', (string)$resource['revision']);
  if($dates['created_at']) $body .= line('CREATED', utc($dates['created_at']));
  if($dates['modified_at']) $body .= line('LAST-MODIFIED', utc($dates['modified_at']));
  $body .= line('SUMMARY', $is_travel ? "Travel time" : \icalendar\escape_text($row['title']));
  if(!$is_travel && $row['content'] !== null && $row['content'] !== "")
    $body .= line('DESCRIPTION', \icalendar\escape_text($row['content']));
  if(!$is_travel)
    $body .= line('PRIORITY', (string)priority($type, $row['id'], \cast_boolean($row['urgent'])));

  $skip = ['UID', 'DTSTAMP', 'SEQUENCE', 'CREATED', 'LAST-MODIFIED', 'SUMMARY', 'DESCRIPTION', 'PRIORITY'];

  if($is_travel) {
    if($type == 'travel_before') {
      $ends_at = new \DateTimeImmutable($row['starts_at']);
      $starts_at = $ends_at->modify('-' . (int)$row['travel_before'] . ' minutes');
    }
    else {
      $starts_at = new \DateTimeImmutable($row['ends_at']);
      $ends_at = $starts_at->modify('+' . (int)$row['travel_after'] . ' minutes');
    }

    if($row['recurrence']) {
      $timezone = [['name' => 'TZID', 'values' => [TIMEZONE]]];
      $body .= line('DTSTART', local_time($starts_at->format('c')), $timezone);
      $body .= line('DTEND', local_time($ends_at->format('c')), $timezone);
      $body .= line('RRULE', $row['recurrence']);
    }
    else {
      $body .= line('DTSTART', utc($starts_at->format('c')));
      $body .= line('DTEND', utc($ends_at->format('c')));
    }
  }
  elseif($type == 'appointment') {
    if(\cast_boolean($row['all_day'])) {
      $body .= line('DTSTART', local_day($row['starts_at']), [['name' => 'VALUE', 'values' => ['DATE']]]);
      $body .= line('DTEND', local_day($row['ends_at']), [['name' => 'VALUE', 'values' => ['DATE']]]);
    }
    elseif($row['recurrence']) {
      $timezone = [['name' => 'TZID', 'values' => [TIMEZONE]]];
      $body .= line('DTSTART', local_time($row['starts_at']), $timezone);
      $body .= line('DTEND', local_time($row['ends_at']), $timezone);
    }
    else {
      $body .= line('DTSTART', utc($row['starts_at']));
      $body .= line('DTEND', utc($row['ends_at']));
    }
    if($row['location']) $body .= line('LOCATION', \icalendar\escape_text($row['location']));
    if($row['meeting']) {
      $stored_url = null;
      foreach(\store\list_properties($type, $row['id']) as $property)
        if($property['name'] == 'URL') $stored_url = $property;
      if(!$stored_url) $body .= line('URL', $row['meeting']);
      $body .= line('CONFERENCE', $row['meeting'], [['name' => 'VALUE', 'values' => ['URI']]]);
    }
    if($row['recurrence']) $body .= line('RRULE', $row['recurrence']);
    $skip = [...$skip, 'DTSTART', 'DTEND', 'DURATION', 'LOCATION', 'CONFERENCE', 'RRULE'];
  }
  elseif($type == 'task') {
    $timezone = [['name' => 'TZID', 'values' => [TIMEZONE]]];
    $body .= $row['recurrence']
      ? line('DTSTART', local_time($row['open_at']), $timezone)
      : line('DTSTART', utc($row['open_at']));
    if($row['due_at']) {
      if(\cast_boolean($row['due_all_day']))
        $body .= line('DUE', local_day($row['due_at']), [['name' => 'VALUE', 'values' => ['DATE']]]);
      elseif($row['recurrence']) $body .= line('DUE', local_time($row['due_at']), $timezone);
      else $body .= line('DUE', utc($row['due_at']));
    }
    $status = $row['status'] == 'done' ? 'COMPLETED' : 'NEEDS-ACTION';
    $body .= line('STATUS', $status);
    if($status == 'COMPLETED' && $row['updated_date']) $body .= line('COMPLETED', utc($row['updated_date']));
    if($row['recurrence']) $body .= line('RRULE', $row['recurrence']);
    $skip = [...$skip, 'DTSTART', 'DUE', 'STATUS', 'COMPLETED', 'RRULE'];
  }
  else {
    $body .= line('DTSTART', utc($row['added_at']));
    $status = $row['status'] == 'bought' ? 'COMPLETED' : 'NEEDS-ACTION';
    $body .= line('STATUS', $status);
    if($status == 'COMPLETED' && $row['updated_date']) $body .= line('COMPLETED', utc($row['updated_date']));
    $skip = [...$skip, 'DTSTART', 'STATUS', 'COMPLETED'];
  }

  if(!$is_travel) {
    $body .= unknown_lines($type, $row['id'], $skip);
    $body .= alarm_lines($type, $row['id']);
  }
  $body .= "END:$component\r\nEND:VCALENDAR\r\n";
  return $body;
}

function etag($body) {
  return '"' . hash('sha256', $body) . '"';
}

function parameters(Property $property) {
  $result = [];
  foreach($property->parameters() as $parameter) $result[] = [
    'name' => strtoupper($parameter->name),
    'values' => array_values($parameter->getParts()),
  ];
  return $result;
}

function properties(Component $component, $native) {
  $result = [];
  foreach($component->children() as $child) {
    if(!$child instanceof Property || in_array($child->name, $native)) continue;
    $result[] = [
      'name' => strtoupper($child->name),
      'parameters' => parameters($child),
      'value' => $child->getRawMimeDirValue(),
    ];
  }
  return $result;
}

function prop(Component $component, $name) {
  $values = $component->select($name);
  return $values ? $values[0] : null;
}

function text(Component $component, $name, $default = null) {
  $property = prop($component, $name);
  return $property ? $property->getValue() : $default;
}

function datetime(Property $property, $allow_date = false) {
  $raw = $property->getRawMimeDirValue();
  $date = $property->getValueType() == 'DATE' || preg_match('/^\d{8}$/', $raw);
  if($date) {
    if(!$allow_date) throw new \InvalidArgumentException("DATE value is not supported here.");
    $value = \DateTimeImmutable::createFromFormat('!Ymd', $raw, new \DateTimeZone(TIMEZONE));
    if(!$value) throw new \InvalidArgumentException("Invalid DATE value.");
    return [$value->setTimezone(new \DateTimeZone("UTC"))->format('c'), true];
  }

  if(isset($property['TZID'])) {
    $tzid = (string)$property['TZID'];
    if($tzid != TIMEZONE)
      throw new \InvalidArgumentException("Only the configured application timezone is supported.");
    $value = \DateTimeImmutable::createFromFormat('!Ymd\THis', $raw, new \DateTimeZone(TIMEZONE));
    if(!$value) throw new \InvalidArgumentException("Invalid DATE-TIME value.");
    return [$value->setTimezone(new \DateTimeZone("UTC"))->format('c'), false];
  }

  if(!str_ends_with($raw, 'Z'))
    throw new \InvalidArgumentException("Floating time values are unsupported.");
  $value = \DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $raw, new \DateTimeZone("UTC"));
  if(!$value) throw new \InvalidArgumentException("Invalid DATE-TIME value.");
  return [$value->format('c'), false];
}

function parse_interval($value) {
  $negative = str_starts_with($value, '-');
  try { $interval = new \DateInterval(ltrim($value, '+-')); }
  catch(\Throwable $e) { throw new \InvalidArgumentException("Invalid duration."); }
  $interval->invert = $negative ? 1 : 0;
  return $interval;
}

function interval_seconds($value) {
  $origin = new \DateTimeImmutable('@0');
  return $origin->add(parse_interval($value))->getTimestamp();
}

function parse_alarm(Component $component) {
  // Alarm delivery is not modeled, so every action becomes a display reminder.
  $action = strtoupper(text($component, 'ACTION', ''));
  if($action == 'NONE') {
    \logger\warn("Received CalDAV alarm action NONE; ignored.");
    return null;
  }
  if($action != 'DISPLAY')
    \logger\warn("Received CalDAV alarm action " . ($action ?: "(missing)") . "; cast to DISPLAY.");
  foreach(['REPEAT', 'DURATION', 'ACKNOWLEDGED', 'PROXIMITY'] as $unsupported)
    if(prop($component, $unsupported))
      throw new \InvalidArgumentException("Alarm $unsupported is unsupported.");

  $trigger = prop($component, 'TRIGGER');
  if(!$trigger) throw new \InvalidArgumentException("Alarm TRIGGER is required.");

  $absolute = $trigger->getValueType() == 'DATE-TIME';
  $alarm = [
    'trigger_at' => null,
    'trigger_offset' => null,
    'relative_to' => null,
    'description' => text($component, 'DESCRIPTION', "Reminder"),
    'properties' => properties($component, ['ACTION', 'TRIGGER', 'DESCRIPTION', 'ATTACH', 'SUMMARY', 'ATTENDEE']),
  ];

  if($absolute) [$alarm['trigger_at']] = datetime($trigger);
  else {
    $alarm['trigger_offset'] = interval_seconds($trigger->getRawMimeDirValue());
    $alarm['relative_to'] = strtoupper((string)$trigger['RELATED']) == 'END' ? 'end' : 'start';
  }
  return $alarm;
}

function parse($body, $expected, $type = null) {
  try { $calendar = Reader::read($body); }
  catch(\Throwable $e) { throw new \InvalidArgumentException("Invalid iCalendar object."); }

  if($calendar->name != 'VCALENDAR') throw new \InvalidArgumentException("VCALENDAR is required.");
  if(prop($calendar, 'METHOD')) throw new \InvalidArgumentException("Scheduling objects are unsupported.");
  $components = [];
  foreach($calendar->getComponents() as $component) {
    if(in_array($component->name, ['VEVENT', 'VTODO'])) $components[] = $component;
    elseif($component->name != 'VTIMEZONE' || text($component, 'TZID') != TIMEZONE)
      throw new \InvalidArgumentException("Unsupported component {$component->name}.");
  }
  if(count($components) != 1 || $components[0]->name != $expected)
    throw new \InvalidArgumentException("Exactly one $expected component is required.");

  $component = $components[0];
  foreach($component->getComponents() as $nested)
    if($nested->name != 'VALARM') throw new \InvalidArgumentException("Unsupported nested component {$nested->name}.");

  foreach(['RECURRENCE-ID', 'RDATE', 'EXDATE', 'ORGANIZER', 'ATTENDEE'] as $unsupported)
    if(prop($component, $unsupported)) throw new \InvalidArgumentException("$unsupported is unsupported.");

  $uid = text($component, 'UID');
  if(!$uid) throw new \InvalidArgumentException("UID is required.");

  $data = [
    'uid' => $uid,
    'title' => text($component, 'SUMMARY', ""),
    'content' => text($component, 'DESCRIPTION'),
    'urgent' => (int)text($component, 'PRIORITY', 0) > 0
      && (int)text($component, 'PRIORITY', 0) < 9,
    'recurrence' => text($component, 'RRULE'),
    'status' => $expected == 'VTODO'
      ? strtoupper(text($component, 'STATUS', 'NEEDS-ACTION')) : null,
    'alarms' => array_values(array_filter(array_map(
      fn($alarm) => parse_alarm($alarm),
      $component->select('VALARM')
    ))),
  ];

  if($expected == 'VTODO' && !in_array($data['status'], ['NEEDS-ACTION', 'COMPLETED']))
    throw new \InvalidArgumentException("Unsupported STATUS value.");

  if($expected == 'VEVENT') {
    $start = prop($component, 'DTSTART');
    if(!$start) throw new \InvalidArgumentException("DTSTART is required.");
    [$data['starts_at'], $data['all_day']] = datetime($start, allow_date: true);

    $end = prop($component, 'DTEND');
    $duration = prop($component, 'DURATION');
    if($end && $duration) throw new \InvalidArgumentException("DTEND and DURATION are mutually exclusive.");
    if($end) [$data['ends_at'], $end_date] = datetime($end, allow_date: true);
    elseif($duration) {
      $zone = $data['all_day'] ? new \DateTimeZone(TIMEZONE) : new \DateTimeZone("UTC");
      $start_date = (new \DateTimeImmutable($data['starts_at']))->setTimezone($zone);
      $data['ends_at'] = $start_date->add(parse_interval($duration->getRawMimeDirValue()))
        ->setTimezone(new \DateTimeZone("UTC"))->format('c');
      $end_date = $data['all_day'];
    }
    else {
      $start_date = new \DateTimeImmutable($data['starts_at']);
      $data['ends_at'] = $data['all_day']
        ? $start_date->setTimezone(new \DateTimeZone(TIMEZONE))->modify('+1 day')->setTimezone(new \DateTimeZone("UTC"))->format('c')
        : $data['starts_at'];
      $end_date = $data['all_day'];
    }
    if($data['all_day'] != $end_date) throw new \InvalidArgumentException("DTSTART and DTEND value types differ.");
    if(strtotime($data['ends_at']) < strtotime($data['starts_at'])) throw new \InvalidArgumentException("DTEND precedes DTSTART.");

    $data['location'] = text($component, 'LOCATION');
    $conference = text($component, 'CONFERENCE');
    $url = text($component, 'URL');
    $data['meeting'] = $conference ?: $url;
    $native = ['UID', 'DTSTAMP', 'SEQUENCE', 'CREATED', 'LAST-MODIFIED', 'SUMMARY', 'DESCRIPTION', 'DTSTART', 'DTEND', 'DURATION', 'LOCATION', 'CONFERENCE', 'RRULE'];
    if(!$conference || $conference == $url) $native[] = 'URL';
  }
  else {
    $start = prop($component, 'DTSTART');
    $data['has_start'] = (bool)$start;
    $data['open_at'] = $start ? datetime($start)[0] : gmdate('c');
    $due = prop($component, 'DUE');
    if($due) [$data['due_at'], $data['due_all_day']] = datetime($due, allow_date: true);
    else [$data['due_at'], $data['due_all_day']] = [null, false];
    $native = ['UID', 'DTSTAMP', 'SEQUENCE', 'CREATED', 'LAST-MODIFIED', 'SUMMARY', 'DESCRIPTION', 'DTSTART', 'STATUS', 'COMPLETED'];
    if($type == 'task') $native = [...$native, 'DUE', 'RRULE'];
    elseif($data['recurrence']) throw new \InvalidArgumentException("Wishlist recurrence is unsupported.");
  }

  foreach($data['alarms'] as $alarm)
    if($alarm['relative_to'] == 'end' && $expected == 'VTODO' && (!$data['due_at'] || $type == 'wish'))
      throw new \InvalidArgumentException("End-relative alarm requires a task due date.");

  $base = new \DateTimeImmutable(@$data['starts_at'] ?: @$data['due_at'] ?: $data['open_at']);
  if($data['recurrence'] && !\recurrence\valid($data['recurrence'], $base->setTimezone(new \DateTimeZone(TIMEZONE))))
    throw new \InvalidArgumentException("Invalid RRULE.");

  $data['properties'] = properties($component, $native);
  return $data;
}
