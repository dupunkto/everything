<?php
// Minimal iCalendar (RFC 5545) parser.
// Written by Claude, don't judge me ok.

namespace ical;

// Fetches and parses an iCalendar feed. Returns what parse_feed returns,
// or null when the URL is unreachable or not an iCalendar feed.
function fetch_feed($url) {
  $response = \http\get($url);
  if($response['state'] != 'success' || $response['status'] >= 400) {
    \logger\warn("ical: fetch failed for $url (status {$response['status']})");
    return null;
  }

  $feed = parse_feed($response['body']);
  if($feed === null) \logger\warn("ical: $url is not an iCalendar feed");

  return $feed;
}

// Parses an iCalendar document into the calendar's display name and color
// (null when the feed doesn't carry them) plus a list of events. Returns
// null when the body is not an iCalendar feed at all (e.g. a captive portal
// or login page).
function parse_feed($body) {
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

    // Subcomponents (VALARM, VTIMEZONE, ...) carry their own DESCRIPTION
    // and friends; skip them wholesale so they don't clobber anything.
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

  return normalize_calendar($calendar, $events);
}

function normalize_calendar($props, $events) {
  $value = fn($name) => isset($props[$name]) ? trim($props[$name]['value']) : null;

  $title = $value("X-WR-CALNAME");

  // Apple publishes a hex color; RFC 7986 prescribes CSS named colors.
  $color = hex_color($value("X-APPLE-CALENDAR-COLOR") ?? "")
    ?? css_named_to_hex($value("COLOR") ?? "");

  return [
    'title' => $title ? unescape_text($title) : null,
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
  $ends_at ??= $all_day ? $starts_at + 86400 : $starts_at;

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
  $local = new \DateTimeZone(getenv("TIMEZONE") ?: "Europe/Amsterdam");

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

function unescape_text($value) {
  return strtr($value, ['\\\\' => "\\", '\\n' => "\n", '\\N' => "\n", '\\,' => ",", '\\;' => ";"]);
}

// Apple ships #RRGGBBAA; strip the alpha so color inputs accept the value.
function hex_color($value) {
  if(!preg_match('/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/', $value)) return null;
  return strtolower(substr($value, 0, 7));
}

// The CSS3 extended palette subset RFC 7986 expects.
// Returns null if the name is unrecognised.
function css_named_to_hex($name) {
  static $map = [
    "black" => "#000000", "silver" => "#c0c0c0", "gray" => "#808080",
    "white" => "#ffffff", "maroon" => "#800000", "red" => "#ff0000",
    "purple" => "#800080", "fuchsia" => "#ff00ff", "green" => "#008000",
    "lime" => "#00ff00", "olive" => "#808000", "yellow" => "#ffff00",
    "navy" => "#000080", "blue" => "#0000ff", "teal" => "#008080",
    "aqua" => "#00ffff", "orange" => "#ffa500", "pink" => "#ffc0cb",
    "cyan" => "#00ffff", "magenta" => "#ff00ff", "indigo" => "#4b0082",
    "violet" => "#ee82ee", "gold" => "#ffd700", "coral" => "#ff7f50",
    "tomato" => "#ff6347", "salmon" => "#fa8072", "khaki" => "#f0e68c",
  ];
  return $map[strtolower($name)] ?? null;
}
