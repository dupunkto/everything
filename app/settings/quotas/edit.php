<?php

  $quota = \store\get_quota_by_tag(cast_int($_POST['tag_id']))
    or fail("Tracker quota not found.", status: 404);

  $period = cast_string($_POST['period']);
  $hours = cast_int($_POST['hours']);
  $remainder = cast_int($_POST['minutes']);
  $start_date = cast_date($_POST['start_date']);

  if(!in_array($period, ['week', 'month']) || is_null($hours) || is_null($remainder) ||
    $hours < 0 || $remainder < 0 || $remainder > 59 || !$start_date)
    fail("Invalid tracker quota.", status: 400);

  $minutes = $hours * 60 + $remainder;
  if($minutes < 1) fail("Invalid tracker quota.", status: 400);

  $fields = \core\diff($quota,
    period: $period, minutes: $minutes, start_date: $start_date);

  \store\update_quota(cast_int($_POST['tag_id']), $period, $minutes, $start_date)
    or fail("Could not save tracker quota.", status: 400);
  \store\put_audit_log('quotas', cast_int($_POST['tag_id']),
    "Updated [" . join(", ", $fields) . "] for quotas/{$_POST['tag_id']}.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
