<?php
  // Drag-to-resize target for the week view: rewrites an appointment's start
  // and end from the dragged edges, nothing else.
  $appointment = \store\get_appointment(@$_POST['id'])
    or fail("Appointment not found.", status: 404);

  // Only calendar-owned, non-recurring appointments are resizable this way.
  // Subscription events belong to their feed, and resizing one occurrence of a
  // series would shift the whole master; both go through the full editor.
  if(!empty($appointment['subscription_id']) || !empty($appointment['recurrence']))
    fail("This appointment can't be resized.", status: 403);

  $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

  \store\update_appointment_times($appointment['id'], $starts_at, $ends_at)
    or fail("Could not resize appointment.");

  // The caller re-fetches the week itself; nothing to render back.
  http_response_code(204); exit;
