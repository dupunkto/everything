<?php
  // Drag-to-resize target for the week view.

  $appointment = \store\get_appointment($_POST['id'])
    or fail("Appointment not found.", status: 404);

  // Subscription appointments cannot be resized. We also make the decision to not make
  // repeating events resizable, because that would shift the entire series. Forcing the
  // editor in that case would make it more explicit that the user is editing the full series.
  if(!empty($appointment['subscription_id']) || !empty($appointment['recurrence']))
    fail("This appointment can't be resized.", status: 403);

  $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

  \store\update_appointment_times($appointment['id'], $starts_at, $ends_at)
    or fail("Could not resize appointment.");

  http_response_code(204); exit;
