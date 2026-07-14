<?php
// Recurrence logic for calendar and todo applications.
// This file was lovingly written by Claude.

namespace recurrence;

// A recurrence is stored as one of two shapes (see the `recurrence` columns):
//   - an interval: a bare positive integer N, meaning "every N days";
//   - a cron expression: five fields `m h dom mon dow`.
//
// Cron fields understand `*`, single values, `a-b` ranges, `a,b` lists and
// `*/s` / `a-b/s` steps. Day-of-week is 0-6 (Sunday 0), with 7 also Sunday.
// When both day-of-month and day-of-week are restricted, they OR together —
// standard cron semantics.

function is_interval($recurrence) {
  return $recurrence !== null && $recurrence !== "" && ctype_digit((string)$recurrence);
}

function describe($recurrence) {
  if($recurrence == null || $recurrence == "") return null;

  if(is_interval($recurrence)) {
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

// True when $value satisfies a single cron field (a comma list of terms).
function field_matches($field, $value, $min, $max, $dow = false) {
  foreach(explode(",", (string)$field) as $term) {
    $term = trim($term);
    if($term === "") continue;

    $step = 1;
    if(str_contains($term, "/")) {
      [$term, $s] = explode("/", $term, 2);
      $step = max(1, (int)$s);
    }

    if($term == "*") {
      $lo = $min; $hi = $max;
    } elseif(str_contains($term, "-")) {
      [$lo, $hi] = array_map('intval', explode("-", $term, 2));
    } else {
      $lo = $hi = (int)$term;
    }

    for($v = $lo; $v <= $hi; $v += $step) {
      if(($dow ? $v % 7 : $v) == $value) return true;
    }
  }

  return false;
}

function matches($cron, \DateTimeInterface $dt) {
  $fields = preg_split('/\s+/', trim((string)$cron));
  if(count($fields) != 5) return false;

  [$minute, $hour, $dom, $month, $dow] = $fields;

  if(!field_matches($minute, (int)$dt->format('i'), 0, 59)) return false;
  if(!field_matches($hour, (int)$dt->format('G'), 0, 23)) return false;
  if(!field_matches($month, (int)$dt->format('n'), 1, 12)) return false;

  $dom_match = field_matches($dom, (int)$dt->format('j'), 1, 31);
  $dow_match = field_matches($dow, (int)$dt->format('w'), 0, 6, dow: true);

  // Restricting both day fields matches either (cron's historical quirk);
  // a wildcard on one side means the other must match on its own.
  return (trim($dom) == "*" || trim($dow) == "*")
    ? ($dom_match && $dow_match)
    : ($dom_match || $dow_match);
}

// First cron firing strictly after $after. Null if none within ~4 years.
function next_cron($cron, \DateTimeInterface $after) {
  $dt = \DateTimeImmutable::createFromInterface($after)
    ->setTime((int)$after->format('H'), (int)$after->format('i'), 0)
    ->modify('+1 minute');

  $limit = $dt->modify('+1500 days');
  while($dt < $limit) {
    if(matches($cron, $dt)) return $dt;
    $dt = $dt->modify('+1 minute');
  }

  return null;
}

// Latest cron firing at or before $before. Null if none within ~4 years.
function previous_cron($cron, \DateTimeInterface $before) {
  $dt = \DateTimeImmutable::createFromInterface($before)
    ->setTime((int)$before->format('H'), (int)$before->format('i'), 0);

  $limit = $dt->modify('-1500 days');
  while($dt > $limit) {
    if(matches($cron, $dt)) return $dt;
    $dt = $dt->modify('-1 minute');
  }

  return null;
}

// Occurrence starts of $recurrence falling within [$from, $to], anchored at
// $base (the series' first occurrence). $until clamps the tail; $count limits
// the total number of occurrences counted from $base. Returns DateTimeImmutable[].
function occurrences(
  $recurrence,
  \DateTimeImmutable $base,
  \DateTimeImmutable $from,
  \DateTimeImmutable $to,
  ?\DateTimeImmutable $until = null,
  ?int $count = null
) {
  if($recurrence === null || $recurrence === "") return [];

  return is_interval($recurrence)
    ? interval_occurrences((int)$recurrence, $base, $from, $to, $until, $count)
    : cron_occurrences($recurrence, $base, $from, $to, $until, $count);
}

function interval_occurrences($days, $base, $from, $to, $until, $count) {
  if($days < 1) return [];

  $step = new \DateInterval("P{$days}D");
  $occ = $base;
  $index = 0;

  // Skip whole periods up front so a distant base doesn't cost a step per day.
  if($occ < $from) {
    $skip = intdiv((int) floor(($from->getTimestamp() - $occ->getTimestamp()) / 86400), $days);
    if($skip > 0) {
      $occ = $base->add(new \DateInterval("P" . ($skip * $days) . "D"));
      $index = $skip;
    }
    while($occ < $from) { $occ = $occ->add($step); $index++; }
  }

  $result = [];
  while($occ <= $to) {
    if($count !== null && $index >= $count) break;
    if($until !== null && $occ > $until) break;
    $result[] = $occ;
    $occ = $occ->add($step);
    $index++;
  }

  return $result;
}

function cron_occurrences($cron, $base, $from, $to, $until, $count) {
  $result = [];

  // A count-limited series can only be answered by walking from its start;
  // there is no closed form for "the Nth cron firing".
  if($count !== null) {
    $occ = $base;
    for($i = 0; $i < $count && $occ !== null && $occ <= $to; $i++) {
      if($occ >= $from && ($until === null || $occ <= $until)) $result[] = $occ;
      $occ = next_cron($cron, $occ);
    }
    return $result;
  }

  $occ = next_cron($cron, $from->modify('-1 minute'));
  while($occ !== null && $occ <= $to) {
    if($occ >= $base && ($until === null || $occ <= $until)) $result[] = $occ;
    $occ = next_cron($cron, $occ);
  }

  return $result;
}

// Resolves a task's effective state from its raw log fields. A recurring task
// resets to 'todo' once its current period elapses; 'blocked' is sticky and
// ignores periods. The task only surfaces in listings within
// TODO_RECURRENCE_HORIZON days of its next deadline.
//
// Expects the store-shaped row: recurrence, status + updated_date (latest log,
// UTC), last_done (UTC, nullable), due_date + open_date (local wall time).
// Returns ['status', 'next', 'previous', 'visible']; 'next'/'previous' are
// local wall-time strings (or null).
function task_state($task) {
  $tz = new \DateTimeZone(TIMEZONE);
  $utc = new \DateTimeZone("UTC");

  $from_utc = fn($v) => $v ? (new \DateTimeImmutable($v, $utc))->setTimezone($tz) : null;
  $from_local = fn($v) => $v ? new \DateTimeImmutable($v, $tz) : null;
  $fmt = fn($dt) => $dt?->format('Y-m-d H:i:s');

  $recurrence = trim((string)$task['recurrence']);
  $latest_status = $task['status'];

  // Non-recurring tasks keep their raw latest status and stay always visible.
  if($recurrence === "") {
    return ['status' => $latest_status, 'next' => $task['due_date'], 'previous' => null, 'visible' => true];
  }

  $now = new \DateTimeImmutable('now', $tz);
  $latest_date = $from_utc($task['updated_date']);

  if(is_interval($recurrence)) {
    $anchor = $from_utc(@$task['last_done'])
      ?? $from_local(@$task['due_date'])
      ?? $from_local(@$task['open_date'])
      ?? $now;
    $next = $anchor->modify("+" . (int)$recurrence . " days");
    $previous = $anchor;
  } else {
    $next = next_cron($recurrence, $now);
    $previous = previous_cron($recurrence, $now);
  }

  if($latest_status == 'blocked') {
    $status = 'blocked';
  } elseif($latest_date && $previous && $next
    && $latest_date >= $previous && $latest_date < $next && $now < $next) {
    $status = $latest_status;
  } else {
    $status = 'todo';
  }

  $horizon = (int) \TODO_RECURRENCE_HORIZON;
  $visible = $status == 'blocked'
    || ($next && $now >= $next->modify("-$horizon days"));

  return [
    'status' => $status,
    'next' => $fmt($next),
    'previous' => $fmt($previous),
    'visible' => $visible,
  ];
}
