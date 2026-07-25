<?php
// Minimal iCalendar (RFC 5545) parser.

namespace icalendar;

function parse_feed($body) {
  // The body is not an iCalendar feed at all (probably a captive
  // portal or login page or something...)
  if(!str_contains($body, "BEGIN:VCALENDAR")) return null;

  // RFC 5545 folds long lines across multiple physical lines using
  // CRLF + leading whitespace. Unfold before scanning.
  $unfolded = preg_replace("/\r?\n[ \t]/", "", $body);
  $lines = preg_split("/\r?\n/", $unfolded);

  $calendar = []; // property map of the VCALENDAR itself
  $events = [];
  $event = null;  // property map of the VEVENT being scanned
  $depth = 0;     // nesting depth of subcomponents to skip

  foreach($lines as $line) {
    $marker = trim($line);
    if($marker == "BEGIN:VCALENDAR" || $marker == "END:VCALENDAR") continue;

    if($marker == "BEGIN:VEVENT" && $event === null && $depth == 0) {
      $event = []; continue;
    }

    // Subcomponents (VALARM, VTIMEZONE, ...) have their own DESCRIPTION etc.
    // So we skip them entirely so those don't accidentally leak into events.
    if(str_starts_with($marker, "BEGIN:")) { $depth++; continue; }

    if($marker == "END:VEVENT" && $event !== null && $depth == 0) {
      $normalized = normalize_event($event);
      if($normalized) $events[] = $normalized;
      $event = null; continue;
    }

    if(str_starts_with($marker, "END:")) {
      if($depth > 0) $depth--;
      continue;
    }

    if($depth > 0) continue;

    $property = parse_property($line);
    if(!$property) continue;

    if($event !== null) $event[$property['name']] = $property;
    else $calendar[$property['name']] = $property;
  }

  $title = unescape_text(@$calendar["X-WR-CALNAME"]['value']);
  $color = normalize_color(@$calendar["X-APPLE-CALENDAR-COLOR"]['value'])
    ?? named_color(@$calendar["COLOR"]['value']);

  return [
    'title' => $title,
    'color' => $color,
    'events' => $events,
  ];
}

// Splits a content line into name, parameters and value. The value starts at
// the first ':' outside quotes; parameters are ';'-separated in the head.
function parse_property($line) {
  if(!preg_match('/^([^:"]*(?:"[^"]*"[^:"]*)*):(.*)$/', $line, $match)) return null;
  [, $head, $value] = $match;

  $segments = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $head);
  $name = strtoupper(trim(array_shift($segments)));
  if($name == "") return null;

  $params = [];
  foreach($segments as $segment) {
    [$key, $val] = explode("=", $segment, 2) + [null, null];
    if($val === null) continue;
    $params[strtoupper(trim($key))] = trim($val, '"');
  }

  return ['name' => $name, 'params' => $params, 'value' => $value];
}

function normalize_event($props) {
  $value = fn($name) => $props[$name]['value'] ?? null;

  if(!$value("UID") || !isset($props['DTSTART'])) return null;

  [$starts_at, $all_day] = resolve_datetime($props['DTSTART']);
  if($starts_at === null) return null;

  $ends_at = null;
  if(isset($props['DTEND'])) [$ends_at] = resolve_datetime($props['DTEND']);

  if($ends_at === null && $value("DURATION")) {
    try {
      $duration = new \DateInterval($value("DURATION"));
      $ends_at = (new \DateTimeImmutable("@$starts_at"))->add($duration)->getTimestamp();
    } catch(\Exception $e) {
      // Malformed duration; fall through to the defaults below.
    }
  }

  // Per RFC 5545, an all-day event without an end lasts one day,
  // a timed one ends immediately.
  if($ends_at === null) {
    $ends_at = $all_day
      ? (new \DateTimeImmutable("@$starts_at"))->setTimezone(new \DateTimeZone(TIMEZONE))->modify('+1 day')->getTimestamp()
      : $starts_at;
  }

  return [
    'uid' => unescape_text($value("UID")),
    'summary' => unescape_text($value("SUMMARY") ?? ""),
    'description' => unescape_text($value("DESCRIPTION") ?? ""),
    'location' => unescape_text($value("LOCATION") ?? ""),
    'conference' => $value("X-GOOGLE-CONFERENCE"),
    'status' => strtoupper($value("STATUS") ?? ""),
    'rrule' => $value("RRULE"),
    'is_exception' => isset($props['RECURRENCE-ID']),
    'starts_at' => $starts_at,
    'ends_at' => max($ends_at, $starts_at),
    'all_day' => $all_day,
  ];
}

// Resolves a DTSTART/DTEND property to [unix timestamp, all_day].
// All-day DATE values are interpreted at local midnight, matching how the
// calendar views treat all-day appointments.
function resolve_datetime($prop) {
  $value = trim($prop['value']);
  $local = new \DateTimeZone(TIMEZONE);

  if(($prop['params']['VALUE'] ?? "") == 'DATE' || preg_match('/^\d{8}$/', $value)) {
    $datetime = \DateTime::createFromFormat('!Ymd', $value, $local);
    return [$datetime ? $datetime->getTimestamp() : null, true];
  }

  if(str_ends_with($value, "Z")) {
    $datetime = \DateTime::createFromFormat('Ymd\THis\Z', $value, new \DateTimeZone("UTC"));
    return [$datetime ? $datetime->getTimestamp() : null, false];
  }

  // TZID-qualified or floating local time.
  $timezone = $local;
  if($tzid = $prop['params']['TZID'] ?? null) {
    try { $timezone = new \DateTimeZone($tzid); }
    catch(\Exception $e) { \logger\warn("ical: unknown TZID '$tzid', assuming local time"); }
  }

  $datetime = \DateTime::createFromFormat('Ymd\THis', $value, $timezone);
  return [$datetime ? $datetime->getTimestamp() : null, false];
}

function escape_text($value) {
  return strtr($value, ["\\" => "\\\\", "\n" => "\\n", "," => "\\,", ";" => "\\;"]);
}

function unescape_text($value) {
  return strtr($value, ['\\\\' => "\\", '\\n' => "\n", '\\N' => "\n", '\\,' => ",", '\\;' => ";"]);
}

