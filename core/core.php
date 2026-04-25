<?php
// Public Everything API.

namespace core;

// Tracker

function new_timing($description, $starts_at, $ends_at, $task_id = null) {
  return \store\put_timing(
    id: generate_humid(),
    description: $description,
    starts_at: $starts_at,
    ends_at: $ends_at,
    task_id: $task_id
  );
}

function list_timings() {
  return \store\list_timings();
}