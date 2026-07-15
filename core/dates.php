<?php
// Date and time helpers.

function local_date($format, $timestamp = "now", $timezone = null) {
  $timezone = $timezone ?: TIMEZONE;
  $datetime = new DateTimeImmutable($timestamp);
  return $datetime->setTimezone(new DateTimeZone($timezone))->format($format);
}
