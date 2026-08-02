<?php
// Scalar casting helpers for data coming from HTML forms and storage.

function is_str($value): bool {
  return cast_str($value) !== null;
}

function is_num($value): bool {
  return $value !== null && is_numeric(trim((string)$value));
}

function is_date($value): bool {
  return cast_date($value) !== null;
}

function cast_str($value): ?string {
  if($value === null) return null;

  $value = trim((string)$value);
  return $value == "" ? null : $value;
}

function cast_num($value): ?int {
  $value = cast_str($value);
  return $value === null ? null : (int)$value;
}

function cast_float($value): ?float {
  $value = cast_str($value);
  return $value === null ? null : (float)$value;
}

function cast_bool($value): bool {
  return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function cast_date($value): ?string {
  $value = cast_str($value);
  if($value === null) return null;

  $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
  return $date && $date->format('Y-m-d') == $value ? $value : null;
}

function cast_color($color): ?string {
  $color = cast_str($color);
  if($color === null) return null;

  return strcasecmp($color, "#ffffff") == 0 ? null : $color;
}

function cast_dt_utc($date, $time, $timezone = null): ?string {
  $date = cast_date($date);
  $time = cast_str($time);
  if($date === null || $time === null) return null;

  $value = strlen($time) == 5 ? "$date $time:00" : "$date $time";
  $datetime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone($timezone ?? TIMEZONE));

  return $datetime && $datetime->format('Y-m-d H:i:s') == $value
    ? $datetime->setTimezone(new DateTimeZone("UTC"))->format('c')
    : null;
}

function cast_dt_local($datetime, $timezone = null): ?string {
  $datetime = cast_str($datetime);
  if($datetime === null) return null;

  $timezone = $timezone ?? TIMEZONE;
  $datetime = new DateTimeImmutable($datetime);
  return $datetime->setTimezone(new DateTimeZone($timezone))->format('Y-m-d\TH:i:s');
}
