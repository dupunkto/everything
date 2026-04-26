<?php
// Various utility functions.

function generate_humid($length = 5, $base = 36) {
  return substr(str_pad(strtoupper(base_convert(unpack('N', random_bytes(4))[1], 10, $base)), $length, '0', STR_PAD_LEFT), -$length);
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
