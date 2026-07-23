<?php
// Helpers for working with quotas in the tracker application.
// This file was lovingly written by Claude.

namespace quotas;

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

function overview($period, \DateTimeImmutable $from, \DateTimeImmutable $to, $quotas): array {
  $quotas = array_filter($quotas, fn($quota) =>
    $quota['period'] == $period && $quota['start_date'] < $to->format("Y-m-d"));

  if(!$quotas) return [];

  $by_tag = [];
  foreach($quotas as $quota) $by_tag[$quota['tag_id']] = $quota;

  $worked = worked_by_tag($by_tag, $from, $to);
  return array_map(function($quota) use ($worked) {
    $quota['worked_seconds'] = $worked[$quota['tag_id']];
    return $quota;
  }, array_values($by_tag));
}

// Average Gregorian month and week length, for scaling a per-period quota
// across the whole span of the total view.
const PERIOD_SECONDS = ['week' => 604800, 'month' => 2629746];

function total(\DateTimeImmutable $now, $quotas): array {
  $quotas = array_filter($quotas, fn($quota) => $quota['start_date'] <= $now->format("Y-m-d"));
  if(!$quotas) return [];

  $by_tag = [];
  foreach($quotas as $quota) $by_tag[$quota['tag_id']] = $quota;

  $earliest = min(array_column($by_tag, 'start_date'));
  $from = new \DateTimeImmutable($earliest, $now->getTimezone());
  $worked = worked_by_tag($by_tag, $from, $now);

  return array_map(function($quota) use ($now, $worked) {
    $start = new \DateTimeImmutable($quota['start_date'], $now->getTimezone());
    $elapsed = max(0, $now->getTimestamp() - $start->getTimestamp());
    $periods = $elapsed / PERIOD_SECONDS[$quota['period']];
    // Scale the per-period target across every period elapsed since the quota began.
    $quota['minutes'] = $quota['minutes'] * $periods;
    $quota['worked_seconds'] = $worked[$quota['tag_id']];
    return $quota;
  }, array_values($by_tag));
}

function worked_by_tag($by_tag, \DateTimeImmutable $from, \DateTimeImmutable $to): array {
  $parents = [];
  foreach(\store\list_tags() as $tag) $parents[$tag['id']] = $tag['parent_id'];

  $from_utc = $from->setTimezone(new \DateTimeZone("UTC"))->format('c');
  $to_utc = $to->setTimezone(new \DateTimeZone("UTC"))->format('c');
  $timings = [];

  foreach(\store\list_timing_tags($from_utc, $to_utc) as $link) {
    $tag_id = $link['tag_id'];
    while($tag_id) {
      if(isset($by_tag[$tag_id])) $timings[$link['id']]['quotas'][$tag_id] = true;
      $tag_id = @$parents[$tag_id];
    }
    $timings[$link['id']]['starts_at'] = $link['starts_at'];
    $timings[$link['id']]['ends_at'] = $link['ends_at'];
  }

  $worked = array_fill_keys(array_keys($by_tag), 0);
  foreach($timings as $timing) {
    foreach(array_keys(@$timing['quotas'] ?: []) as $tag_id) {
      $quota = $by_tag[$tag_id];
      $quota_start = new \DateTimeImmutable($quota['start_date'], $from->getTimezone());
      $effective_from = max($from->getTimestamp(), $quota_start->getTimestamp());
      $starts_at = max($effective_from, (new \DateTimeImmutable($timing['starts_at']))->getTimestamp());
      $ends_at = min($to->getTimestamp(), (new \DateTimeImmutable($timing['ends_at']))->getTimestamp());
      $worked[$tag_id] += max(0, $ends_at - $starts_at);
    }
  }

  return $worked;
}

function duration($seconds): string {
  $minutes = (int)floor(abs($seconds) / 60);
  return intdiv($minutes, 60) . ":" . sprintf("%02d", $minutes % 60);
}
