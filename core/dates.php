<?php
// Date and time helpers.

function local_date($format, $timestamp = "now", $timezone = null) {
  $timezone = new DateTimeZone($timezone ?: TIMEZONE);
  $datetime = new DateTimeImmutable($timestamp, $timezone);
  return $datetime->setTimezone($timezone)->format($format);
}

function utc_timestamp($value) {
  if(!is_nonempty_str($value)) return null;
  try { return (new DateTimeImmutable($value, new DateTimeZone("UTC")))->getTimestamp(); }
  catch(Exception) { return null; }
}

function utc_iso($timestamp) {
  return gmdate('c', $timestamp);
}

function utc_sql($timestamp) {
  return gmdate('Y-m-d H:i:s', $timestamp);
}
