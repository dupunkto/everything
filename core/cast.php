<?php
// Scalar casting helpers for data coming from HTML forms and storage.

function cast_string($value): ?string {
  if($value === null) return null;

  $value = trim((string)$value);
  return $value == "" ? null : $value;
}

function cast_int($value): ?int {
  $value = cast_string($value);
  return $value === null ? null : (int)$value;
}

function cast_float($value): ?float {
  $value = cast_string($value);
  return $value === null ? null : (float)$value;
}

function cast_boolean($value): bool {
  return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function cast_date($value): ?string {
  $value = cast_string($value);
  if($value === null) return null;

  $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
  return $date && $date->format('Y-m-d') == $value ? $value : null;
}

function cast_color($color): ?string {
  $color = cast_string($color);
  if($color === null) return null;

  return strcasecmp($color, "#ffffff") == 0 ? null : $color;
}

function cast_datetime_utc($date, $time, $timezone = null): ?string {
  $date = cast_date($date);
  $time = cast_string($time);
  if($date === null || $time === null) return null;

  $timezone = $timezone ?? TIMEZONE;
  $datetime = new DateTime("$date $time", new DateTimeZone($timezone));
  return $datetime->setTimezone(new DateTimeZone("UTC"))->format('c');
}

function cast_datetime_local($datetime, $timezone = null): ?string {
  $datetime = cast_string($datetime);
  if($datetime === null) return null;

  $timezone = $timezone ?? TIMEZONE;
  $datetime = new DateTimeImmutable($datetime);
  return $datetime->setTimezone(new DateTimeZone($timezone))->format('Y-m-d\TH:i:s');
}
