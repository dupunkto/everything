<?php
// Layouting engine for calender views.
// This file was lovingly written by Claude.

namespace calendar;

// NOTE: All datetimes in here are local wall time formatted "Y-m-d H:i:s",
// so instants order and compare as plain strings. Day collections are keyed
// by "Y-m-d" date, so views of any length share the same shapes.

define('TASK_COLOR', "#cccccc");
define('BIRTHDAY_COLOR', "#cccccc");

function wall($utc) {
  return str_replace("T", " ", \cast_datetime_local($utc));
}

// Every date in [$from, $to) as "Y-m-d" strings.
function dates($from, $to) {
  $dates = [];
  for($day = clone $from; $day < $to; $day->modify('+1 day'))
    $dates[] = $day->format('Y-m-d');
  return $dates;
}

// Appointments overlapping [$from, $to), localised, with recurring series
// expanded into their individual occurrences.
function appointments($from, $to) {
  $utc_from = \cast_datetime_utc($from->format('Y-m-d'), $from->format('H:i:s'));
  $utc_to = \cast_datetime_utc($to->format('Y-m-d'), $to->format('H:i:s'));

  $appointments = \store\list_appointments($utc_from, $utc_to) ?: [];

  foreach($appointments as &$a) {
    $a['starts_at'] = wall($a['starts_at']);
    $a['ends_at'] = wall($a['ends_at']);
  }
  unset($a);

  // Recurrences are expressed in local wall time (that's how the sync derives
  // them), so the master is localised first and enumerated in the same space.
  $window_from = new \DateTimeImmutable($from->format("Y-m-d H:i:s"));
  $window_to = new \DateTimeImmutable($to->format("Y-m-d H:i:s"));

  foreach(\store\list_recurring_appointments($utc_from, $utc_to) ?: [] as $series) {
    $base = new \DateTimeImmutable(wall($series['starts_at']));
    $duration = (new \DateTimeImmutable(wall($series['ends_at'])))->getTimestamp() - $base->getTimestamp();

    $until = $series['recurrence_until'] ? new \DateTimeImmutable(wall($series['recurrence_until'])) : null;
    $count = $series['recurrence_count'] !== null ? (int) $series['recurrence_count'] : null;

    // The lower bound is widened by the duration so occurrences starting just
    // before the window but spilling into it aren't dropped; the upper bound
    // stays exclusive.
    foreach(\recurrence\occurrences(
      $series['recurrence'], $base,
      $window_from->modify("-$duration seconds"),
      $window_to->modify('-1 second'),
      $until, $count
    ) as $occurrence) {
      $series['starts_at'] = $occurrence->format("Y-m-d H:i:s");
      $series['ends_at'] = $occurrence->modify("+$duration seconds")->format("Y-m-d H:i:s");
      $appointments[] = $series;
    }
  }

  return $appointments;
}

function chronological(&$appointments) {
  // Sort by start date, then calendar, then ID.

  usort($appointments, fn($a, $b) =>
    strcmp($a['starts_at'], $b['starts_at'])
    ?: ($a['calendar_id'] <=> $b['calendar_id'])
    ?: ($a['subscription_id'] <=> $b['subscription_id'])
    ?: strcmp($a['id'], $b['id']));
}

function task_deadlines($from, $to) {
  $deadlines = [];

  foreach(\store\list_tasks("not:done not:nvm", [], respect_horizon: false) ?: [] as $task) {
    if(!$task['next']) continue;

    $due = new \DateTime(wall($task['next']));

    if(cast_boolean(@$task['due_all_day'])) {
      $start = (clone $due)->setTime(0, 0);
      $end = (clone $start)->modify('+1 day');
      if($start >= $to || $end <= $from) continue;
    } else {
      if($due <= $from || $due > $to) continue;
      $start = (clone $due)->modify('-45 minutes');
      $end = $due;
    }

    $deadlines[] = [
      'id' => $task['id'],
      'title' => \esc_inner($task['title']),
      'starts_at' => $start->format("Y-m-d H:i:s"),
      'ends_at' => $end->format("Y-m-d H:i:s"),
      'all_day' => cast_boolean(@$task['due_all_day']),
      'going' => true,
      'calendar_id' => null,
      'subscription_id' => null,
      'calendar_color' => TASK_COLOR,
      'location' => null,
      'recurrence' => null,
      'meeting' => false,
      'travel_before' => 0,
      'travel_after' => 0,
      'urgent' => $task['urgent'],
      'is_task' => true,
    ];
  }

  return $deadlines;
}

// Contact birthdays as annually-recurring all-day appointments within
// [$from, $to). Titled with the turning age when the birth year is known,
// a plain birthday otherwise.
function birthdays($from, $to) {
  $ordinal = function($n) {
    $tens = $n % 100;
    $suffix = $tens >= 11 && $tens <= 13 ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');
    return "$n$suffix";
  };

  $birthdays = [];

  foreach(\store\list_contacts() as $contact) {
    if(empty($contact['birth_day']) || empty($contact['birth_month'])) continue;

    for($year = (int) $from->format('Y'); $year <= (int) $to->format('Y'); $year++) {
      $date = \DateTime::createFromFormat('!Y-m-d', join("-", [
        str_pad($year, 4, "0", STR_PAD_LEFT),
        str_pad($contact['birth_month'], 2, "0", STR_PAD_LEFT),
        str_pad($contact['birth_day'], 2, "0", STR_PAD_LEFT),
      ]));

      if(!$date || $date < $from || $date >= $to) continue;

      $age = $contact['birth_year'] ? $year - (int) $contact['birth_year'] : null;
      $name = \esc_inner(\contacts\contact_display_name($contact));

      $birthdays[] = [
        'id' => "birthday-{$contact['id']}-$year",
        'title' => $age >= 1 ? "$name's {$ordinal($age)} birthday" : "$name's birthday",
        'starts_at' => $date->format("Y-m-d 00:00:00"),
        'ends_at' => (clone $date)->modify('+1 day')->format("Y-m-d 00:00:00"),
        'all_day' => true,
        'going' => true,
        'calendar_id' => null,
        'subscription_id' => null,
        'calendar_color' => BIRTHDAY_COLOR,
        'location' => null,
        'recurrence' => null,
        'meeting' => false,
        'urgent' => false,
        'is_birthday' => true,
      ];
    }
  }

  return $birthdays;
}

// Splits appointments over the days they touch, clipped to [$from, $to), into
// a date-keyed map. Segments carry a layout_end enforcing a 30-minute minimum
// for stacking, and an editable flag: drag-resizable only when calendar-owned,
// non-recurring and contained in a single day, so a dragged edge maps cleanly
// onto one start/end.
function day_segments($appointments, $from, $to) {
  $days = array_fill_keys(dates($from, $to), []);

  foreach($appointments as $appointment) {
    $start = new \DateTime($appointment['starts_at']);
    $end = min(new \DateTime($appointment['ends_at']), $to);

    $cursor = max((clone $start)->setTime(0, 0), clone $from);

    while($cursor < $end) {
      $day_end = (clone $cursor)->modify('+1 day');
      $date = $cursor->format('Y-m-d');

      if(isset($days[$date])) {
        $segment_start = max($start, $cursor);
        $segment_end = min($end, $day_end);

        $segment = $appointment;
        $segment['starts_at'] = $segment_start->format("Y-m-d H:i:s");
        $segment['ends_at'] = $segment_end->format("Y-m-d H:i:s");
        $segment['layout_end'] = max($segment_end, (clone $segment_start)->modify('+30 minutes'))->format("Y-m-d H:i:s");
        $segment['editable'] = empty($appointment['subscription_id'])
          && !cast_boolean(@$appointment['is_task'])
          && empty($appointment['recurrence'])
          && $segment_start == $start
          && $segment_end == $end;

        $days[$date][] = $segment;
      }

      $cursor = $day_end;
    }
  }

  return $days;
}

// Vertical placement of a segment as percentages of its day.
function vertical($segment) {
  $start = new \DateTimeImmutable($segment['starts_at']);
  $end = new \DateTimeImmutable($segment['ends_at']);
  $midnight = $start->setTime(0, 0);

  $day_minutes = ($midnight->modify('+1 day')->getTimestamp() - $midnight->getTimestamp()) / 60;

  return [
    'top' => ($start->getTimestamp() - $midnight->getTimestamp()) / 60 / $day_minutes * 100,
    'height' => ($end->getTimestamp() - $start->getTimestamp()) / 60 / $day_minutes * 100,
  ];
}

// Greedy column layout for one day's segments. Going appointments pack into
// columns and expand into free columns to their right; 'not going' ones don't
// participate but overlay the result at (almost) full width, inset a little
// further per appointment they cover so left borders underneath stay visible.
//
// Credits to @m1kadev for implementing this in Python originally, for a
// project that shall not be named on legal grounds. Happily stolen:)
function layout($day) {
  chronological($day);

  $skipped = array_filter($day, fn($a) => !cast_boolean($a['going']));
  $day = array_filter($day, fn($a) => cast_boolean($a['going']));

  $overlaps = fn($a, $b) =>
    $a['starts_at'] < $b['layout_end'] && $a['layout_end'] > $b['starts_at'];

  $columns = [];

  foreach($day as $appointment) {
    foreach($columns as $i => $column) {
      if($column[array_key_last($column)]['layout_end'] <= $appointment['starts_at']) {
        $columns[$i][] = $appointment;
        continue 2;
      }
    }
    $columns[] = [$appointment];
  }

  $total = max(count($columns), 1);
  $placed = [];

  foreach($columns as $i => $column) {
    foreach($column as $appointment) {
      $span = 1;
      for($k = $i + 1; $k < $total; $k++) {
        if(array_any($columns[$k], fn($other) => $overlaps($appointment, $other))) break;
        $span++;
      }

      $appointment['layout'] = vertical($appointment) + [
        'width' => $span / $total * 100,
        'left' => $i / $total * 100,
      ];
      $placed[] = $appointment;
    }
  }

  foreach($skipped as $appointment) {
    $level = count(array_filter($placed, fn($other) => $overlaps($appointment, $other)));
    $appointment['layout'] = vertical($appointment) + ['inset' => $level * 5];
    $placed[] = $appointment;
  }

  return $placed;
}

// All-day appointments as grid placements over $days columns starting at
// $from, rows assigned first-fit. One ending at midnight ends the day before.
function all_day_lanes($appointments, $from, $days = 7) {
  chronological($appointments);

  $last = (clone $from)->modify('+' . ($days - 1) . ' days');
  $lanes = []; // rightmost occupied column per row
  $placed = [];

  foreach($appointments as $appointment) {
    $start = max($from, (new \DateTime($appointment['starts_at']))->setTime(0, 0));

    $end = new \DateTime($appointment['ends_at']);
    if($end->format('H:i:s') == "00:00:00") $end->modify('-1 day');
    $end = min($last, $end->setTime(0, 0));

    if($end < $start) continue;

    $column = $from->diff($start)->days + 1;
    $span = $start->diff($end)->days + 1;

    $row = 1;
    while(($lanes[$row] ?? 0) >= $column) $row++;
    $lanes[$row] = $column + $span - 1;

    $appointment['layout'] = ['column' => $column, 'span' => $span, 'row' => $row];
    $placed[] = $appointment;
  }

  return $placed;
}

// Clips a wall-time interval to [$from, $to) and splits it over the days it
// touches, as date => [start minute, end minute].
function clip($start, $end, $from, $to) {
  $start = max($start, $from);
  $end = min($end, $to);

  $segments = [];
  $cursor = (clone $start)->setTime(0, 0);

  while($cursor < $end) {
    $day_end = (clone $cursor)->modify('+1 day');
    $segments[$cursor->format('Y-m-d')] = [
      (max($start, $cursor)->getTimestamp() - $cursor->getTimestamp()) / 60,
      (min($end, $day_end)->getTimestamp() - $cursor->getTimestamp()) / 60,
    ];
    $cursor = $day_end;
  }

  return $segments;
}

// Travel windows around appointments as date-keyed top/height percentages,
// merged per day so adjacent bands never stack into a darker patch.
function travel_bands($appointments, $from, $to) {
  $bands = array_fill_keys(dates($from, $to), []);

  foreach($appointments as $appointment) {
    if(!cast_boolean($appointment['going'])) continue;

    $windows = [];

    if($before = (int) $appointment['travel_before']) {
      $start = new \DateTime($appointment['starts_at']);
      $windows[] = [(clone $start)->modify("-$before minutes"), $start];
    }

    if($after = (int) $appointment['travel_after']) {
      $end = new \DateTime($appointment['ends_at']);
      $windows[] = [$end, (clone $end)->modify("+$after minutes")];
    }

    foreach($windows as [$start, $end])
      foreach(clip($start, $end, $from, $to) as $date => $interval)
        $bands[$date][] = $interval;
  }

  foreach($bands as &$intervals) {
    usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);

    $merged = [];
    foreach($intervals as [$start, $end]) {
      $last = array_key_last($merged);
      if($merged && $start <= $merged[$last][1]) $merged[$last][1] = max($merged[$last][1], $end);
      else $merged[] = [$start, $end];
    }

    $intervals = array_map(fn($i) => [
      'top' => $i[0] / 1440 * 100,
      'height' => ($i[1] - $i[0]) / 1440 * 100,
    ], $merged);
  }

  return $bands;
}

// Tracked timings as date-keyed gutter lines (top/height percentages),
// coloured by their first tag's root, grey when untagged.
function timing_lines($from, $to) {
  $tags = [];
  foreach(\store\list_tags() ?: [] as $tag) $tags[$tag['id']] = $tag;

  $root_color = function($id) use ($tags) {
    $tag = $tags[$id] ?? null;
    while($tag && $tag['parent_id']) $tag = $tags[$tag['parent_id']] ?? null;
    return @$tag['color'];
  };

  $lines = array_fill_keys(dates($from, $to), []);

  foreach(\store\list_timings_between(
    \cast_datetime_utc($from->format('Y-m-d'), $from->format('H:i:s')),
    \cast_datetime_utc($to->format('Y-m-d'), $to->format('H:i:s'))
  ) ?: [] as $timing) {
    $start = new \DateTime(wall($timing['starts_at']));
    $end = new \DateTime(wall($timing['ends_at']));

    foreach(clip($start, $end, $from, $to) as $date => [$a, $b]) {
      $lines[$date][] = [
        'top' => $a / 1440 * 100,
        'height' => ($b - $a) / 1440 * 100,
        'color' => $root_color($timing['first_tag_id']) ?: '#cccccc',
        'title' => $timing['description'] ?: "No description",
      ];
    }
  }

  return $lines;
}
