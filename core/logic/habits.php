<?php
// Helpers for the habits application.
// This file was lovingly written by Claude.

namespace habits;

function on_date($habit, $date) {
  if($date < $habit['start_date']) return false;

  $every = max(1, (int) $habit['every']);
  $start = new \DateTimeImmutable($habit['start_date']);
  $day = new \DateTimeImmutable($date);

  return $start->diff($day)->days % $every == 0;
}

function calendar($from, $to) {
  $dates = \calendar\dates($from, $to);
  $habits = \store\list_habits();
  $logs = \store\list_habit_logs_between($from->format('Y-m-d'), $to->format('Y-m-d'));
  $done = [];

  foreach($logs as $log) $done[$log['date']][$log['habit_id']] = true;

  $result = array_fill_keys($dates, []);

  foreach($dates as $date) {
    foreach($habits as $habit) {
      if(!on_date($habit, $date)) continue;

      $habit['date'] = $date;
      $habit['done'] = isset($done[$date][$habit['id']]);
      $habit['contrast_color'] = \contrast_color(
        $habit['color'],
        \lighten($habit['color'], 0.85),
        \darken($habit['color'], 0.65)
      );

      $result[$date][] = $habit;
    }
  }

  return $result;
}
