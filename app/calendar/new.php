<?php
  // Drag-to-create target for the week view.

  if($method != 'POST') fail("Method not allowed.", status: 405);

  if(!CALENDAR_DEFAULT_CALENDAR) {
    fail("No default calendar available.", status: 409);
  }

  if(!isset($_POST['start_date'], $_POST['start_time'], $_POST['end_date'], $_POST['end_time'])) {
    fail("Missing event times.", status: 400);
  }

  $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

  $id = \store\put_calendar_appointment(
    CALENDAR_DEFAULT_CALENDAR,
    "New event",
    null,
    $starts_at,
    $ends_at,
    all_day: cast_boolean(@$_POST['all_day'])
  );

  \store\put_audit_log('appointments', $id, "Created appointments/$id.", 'user', operation: 'insert');

  \caldav\mark_resource_changed('appointment', $id);

  header("Content-Type: text/plain");
  echo $id; // Return the ID. The frontend will use this to open an edit modal.
