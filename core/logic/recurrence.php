<?php

namespace recurrence;

use Sabre\VObject\Recur\RRuleIterator;

function iterator($rrule, $start) {
  return new RRuleIterator($rrule, $start);
}

function valid($rrule, $start = null) {
  if(!$rrule || !preg_match('/(?:^|;)FREQ=(HOURLY|DAILY|WEEKLY|MONTHLY|YEARLY)(?:;|$)/i', $rrule)) return false;
  if(!$start) $start = new \DateTimeImmutable("now", new \DateTimeZone(TIMEZONE));

  try {
    iterator($rrule, $start);
    return true;
  }
  catch(\Throwable $e) {
    return false;
  }
}

function describe($rrule) {
  if(!$rrule) return null;

  $parts = [];
  foreach(explode(";", $rrule) as $part) {
    [$name, $value] = explode("=", $part, 2) + [null, null];
    if($value !== null) $parts[strtoupper($name)] = $value;
  }

  $frequency = match(strtoupper(@$parts['FREQ'])) {
    'HOURLY' => 'hour',
    'DAILY' => 'day',
    'WEEKLY' => 'week',
    'MONTHLY' => 'month',
    'YEARLY' => 'year',
    default => 'recurrence',
  };
  $interval = (int) (@$parts['INTERVAL'] ?: 1);
  return $interval == 1 ? "Every $frequency" : "Every $interval {$frequency}s";
}

function occurrences($rrule, $base, $from, $to) {
  if(!valid($rrule, $base)) return [];

  try {
    $iterator = iterator($rrule, $base);
    $iterator->fastForward($from);
  }
  catch(\Throwable $e) {
    return [];
  }

  $result = [];
  while($iterator->valid()) {
    $occurrence = \DateTimeImmutable::createFromInterface($iterator->current());
    if($occurrence > $to) break;
    $result[] = $occurrence;
    $iterator->next();
  }

  return $result;
}

function neighbours($rrule, $base, $at) {
  if(!valid($rrule, $base)) return [null, null];

  try {
    $iterator = iterator($rrule, $base);
  }
  catch(\Throwable $e) {
    return [null, null];
  }

  $previous = null;
  while($iterator->valid()) {
    $current = \DateTimeImmutable::createFromInterface($iterator->current());
    if($current > $at) return [$previous, $current];
    $previous = $current;
    $iterator->next();
  }

  return [$previous, null];
}

function task_state($task) {
  $tz = new \DateTimeZone(TIMEZONE);
  $utc = new \DateTimeZone("UTC");

  $from_utc = fn($value) => $value
    ? (new \DateTimeImmutable($value, $utc))->setTimezone($tz)
    : null;
  $fmt_utc = fn($date) => $date?->setTimezone($utc)->format('c');

  $rrule = trim((string)$task['recurrence']);
  $latest_status = $task['status'];

  if($rrule == "") {
    return ['status' => $latest_status, 'next' => $task['due_at'], 'previous' => null, 'visible' => true];
  }

  $now = new \DateTimeImmutable("now", $tz);
  $base = $from_utc(@$task['due_at']) ?: $from_utc(@$task['open_at']) ?: $now;
  [$previous, $next] = neighbours($rrule, $base, $now);
  $latest_date = $from_utc($task['updated_date']);

  if($latest_status == 'blocked') {
    $status = 'blocked';
  }
  elseif($latest_date && $previous && $latest_date >= $previous && (!$next || $latest_date < $next)) {
    $status = $latest_status;
  }
  else {
    $status = 'todo';
  }

  $horizon = (int) \TODO_RECURRENCE_HORIZON;
  $visible = $status == 'blocked'
    || ($next && $now >= $next->modify("-$horizon days"));

  return [
    'status' => $status,
    'next' => $fmt_utc($next),
    'previous' => $fmt_utc($previous),
    'visible' => $visible,
  ];
}
