<?php
// Date and time helpers.

function local_date($format, $timestamp = "now", $timezone = null) {
  $timezone = $timezone ?: TIMEZONE;
  $datetime = new DateTimeImmutable($timestamp, new DateTimeZone($timezone));
  return $datetime->format($format);
}
