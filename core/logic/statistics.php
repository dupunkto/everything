<?php
// Tracked time per tag and quota progress for the tracker statistics.
// This file was lovingly written by Claude.

namespace statistics;

function period_start($value, $period): \DateTimeImmutable {
  $timezone = new \DateTimeZone(TIMEZONE);

  try {
    $date = new \DateTimeImmutable($value ?: "today", $timezone);
  }
  catch(\Throwable) {
    $date = new \DateTimeImmutable("today", $timezone);
  }

  $date = $date->setTime(0, 0);
  return $period == 'week'
    ? $date->modify("monday this week")
    : $date->modify("first day of this month");
}

const PERIOD_DAYS = ['week' => 7, 'month' => 30.436875];

function overview($period, \DateTimeImmutable $from, \DateTimeImmutable $to, $quotas): array {
  $quotas = array_filter($quotas, fn($quota) =>
    PERIOD_DAYS[$quota['period']] <= PERIOD_DAYS[$period] && $quota['start_date'] < $to->format("Y-m-d"));

  return array_map(fn($row) => isset($row['minutes']) && $row['period'] != $period
    ? ['minutes' => target($row, $from, $to)] + $row
    : $row, rows($quotas, $from, $to));
}

function total(\DateTimeImmutable $now, $quotas): array {
  $quotas = array_filter($quotas, fn($quota) => $quota['start_date'] <= $now->format("Y-m-d"));
  $rows = rows($quotas, (new \DateTimeImmutable("@0"))->setTimezone($now->getTimezone()), $now);
  $rows = array_filter($rows, fn($row) => isset($row['minutes']) || !$row['parent_id']);
  $week_end = period_start($now->format("Y-m-d"), 'week')->modify("+1 week");

  return array_map(fn($row) => isset($row['minutes'])
    ? ['minutes' => target($row, new \DateTimeImmutable($row['start_date'], $now->getTimezone()), $week_end)] + $row
    : $row, array_values($rows));
}

function target($quota, \DateTimeImmutable $from, \DateTimeImmutable $to): float {
  return $quota['minutes'] * $from->diff($to)->days / PERIOD_DAYS[$quota['period']];
}

// Quota rows first, then every other tag with time tracked in the range.
function rows($quotas, \DateTimeImmutable $from, \DateTimeImmutable $to): array {
  $tags = \store\list_tags();
  $quotas = array_column($quotas, null, 'tag_id');
  $worked = worked_by_tag(array_column($tags, 'parent_id', 'id'), $quotas, $from, $to);

  $rows = array_map(fn($quota) =>
    $quota + ['worked_seconds' => $worked[$quota['tag_id']] ?? 0], array_values($quotas));

  foreach($tags as $tag) {
    if(isset($quotas[$tag['id']]) || empty($worked[$tag['id']])) continue;
    $rows[] = [
      'tag_id' => $tag['id'],
      'parent_id' => $tag['parent_id'],
      'label' => $tag['label'],
      'color' => $tag['color'],
      'worked_seconds' => $worked[$tag['id']],
    ];
  }

  return $rows;
}

// Time per tag, rolled up into its ancestors. A quota only counts time from
// its start date onwards.
function worked_by_tag($parents, $quotas, \DateTimeImmutable $from, \DateTimeImmutable $to): array {
  $from_utc = $from->setTimezone(new \DateTimeZone("UTC"))->format('c');
  $to_utc = $to->setTimezone(new \DateTimeZone("UTC"))->format('c');
  $timings = [];

  foreach(\store\list_timing_tags_between($from_utc, $to_utc) as $link) {
    $tag_id = $link['tag_id'];
    while($tag_id) {
      $timings[$link['id']]['tags'][$tag_id] = true;
      $tag_id = @$parents[$tag_id];
    }
    $timings[$link['id']]['starts_at'] = $link['starts_at'];
    $timings[$link['id']]['ends_at'] = $link['ends_at'];
  }

  $worked = [];
  foreach($timings as $timing) {
    foreach(array_keys($timing['tags']) as $tag_id) {
      $effective_from = $from->getTimestamp();
      if(isset($quotas[$tag_id])) {
        $quota_start = new \DateTimeImmutable($quotas[$tag_id]['start_date'], $from->getTimezone());
        $effective_from = max($effective_from, $quota_start->getTimestamp());
      }
      $starts_at = max($effective_from, (new \DateTimeImmutable($timing['starts_at']))->getTimestamp());
      $ends_at = min($to->getTimestamp(), (new \DateTimeImmutable($timing['ends_at']))->getTimestamp());
      $worked[$tag_id] = ($worked[$tag_id] ?? 0) + max(0, $ends_at - $starts_at);
    }
  }

  return $worked;
}

function duration($seconds): string {
  $minutes = (int)floor(abs($seconds) / 60);
  return intdiv($minutes, 60) . ":" . sprintf("%02d", $minutes % 60);
}
