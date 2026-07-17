<?php
  // Drag-to-resize/move target for the week view.

  $appointment = \store\get_appointment($_POST['id'])
    or fail("Appointment not found.", status: 404);

  // Subscription appointments cannot be moved or resized. We also make the decision to not make
  // repeating events movable or resizable, because that would shift the entire series. Forcing the
  // editor in that case would make it more explicit that the user is editing the full series.
  if(!empty($appointment['subscription_id']) || !empty($appointment['recurrence']))
    fail("This appointment can't be moved or resized.", status: 403);

  $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

  \store\update_appointment_times($appointment['id'], $starts_at, $ends_at)
    or fail("Could not move or resize appointment.");

  http_response_code(204); exit;
