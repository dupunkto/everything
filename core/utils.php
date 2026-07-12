<?php
// Various utility functions.

function generate_humid($length = 5, $base = 36) {
  return substr(str_pad(strtoupper(base_convert(unpack('N', random_bytes(4))[1], 10, $base)), $length, '0', STR_PAD_LEFT), -$length);
}

function normalize_color($color) {
  // Color fields cannot be left blank. We treat #ffffff as "no color" or null.

  if($color === null || $color === "") return null;
  return strcasecmp($color, "#ffffff") == 0 ? null : $color;
}

function cast_boolean($value) {
  return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function cast_datetime_utc($date, $time, $timezone = null) {
  $timezone = $timezone ?? getenv("TIMEZONE") ?: "Europe/Amsterdam";
  $datetime = new DateTime("$date $time", new DateTimeZone($timezone));
  return $datetime->setTimezone(new DateTimeZone("UTC"))->format('c');
}

function cast_datetime_local($datetime, $timezone = null) {
  $timezone = $timezone ?? getenv("TIMEZONE") ?: "Europe/Amsterdam";
  $datetime = new DateTimeImmutable($datetime);
  return $datetime->setTimezone(new DateTimeZone($timezone))->format('Y-m-d\TH:i:s');
}

function local_date($format, $timestamp = "now", $timezone = null) {
  $timezone = $timezone ?? getenv("TIMEZONE") ?: "Europe/Amsterdam";
  $datetime = new DateTimeImmutable($timestamp, new DateTimeZone($timezone));
  return $datetime->format($format);
}

function describe_recurrence($recurrence) {
  if($recurrence === null || $recurrence === "") return null;

  if(ctype_digit((string)$recurrence)) {
    return $recurrence == 1 ? "Every day" : "Every $recurrence days";
  }

  $fields = preg_split('/\s+/', trim($recurrence));
  if(count($fields) != 5) return $recurrence;

  [$minute, $hour, $dom, $month, $dow] = $fields;

  if(preg_match('/[^\d,*]/', implode("", $fields))) return $recurrence;
  if(!ctype_digit($minute) || !ctype_digit($hour)) return $recurrence;

  $time = sprintf("%02d:%02d", $hour, $minute);

  $list = fn($items) => count($items) > 1
    ? implode(", ", array_slice($items, 0, -1)) . " and " . end($items)
    : $items[0];

  if($dow != "*") {
    $weekdays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
    $names = array_map(fn($day) => $weekdays[$day % 7], explode(",", $dow));
    return "Every " . $list($names) . " at $time";
  }

  if($dom != "*" && $month != "*") {
    if(!ctype_digit($dom) || !ctype_digit($month) || $month < 1 || $month > 12) return $recurrence;
    $months = ["January", "February", "March", "April", "May", "June",
               "July", "August", "September", "October", "November", "December"];
    return "Every year on $dom " . $months[$month - 1] . " at $time";
  }

  if($dom != "*") {
    return "Every month on day " . $list(explode(",", $dom)) . " at $time";
  }

  return "Every day at $time";
}
