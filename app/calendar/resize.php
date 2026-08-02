<?php
  // Drag-to-resize/move target for the week view.

  if($method != 'POST') fail("Method not allowed.", status: 405);

  $appointment = \store\get_appointment($_POST['id'])
    or fail("Appointment not found.", status: 404);

  // Subscription appointments cannot be moved or resized. We also make the decision to not make
  // repeating events movable or resizable, because that would shift the entire series. Forcing the
  // editor in that case would make it more explicit that the user is editing the full series.
  if(!empty($appointment['subscription_id']) || !empty($appointment['recurrence']))
    fail("This appointment can't be moved or resized.", status: 403);

  $starts_at = cast_dt_utc($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_dt_utc($_POST['end_date'], $_POST['end_time']);

  \store\update_appointment_times($appointment['id'], $starts_at, $ends_at);

  $fields = \core\diff($appointment, starts_at: $starts_at, ends_at: $ends_at);
  \store\put_audit_log('appointments', $appointment['id'], "Updated [" . join(", ", $fields) . "] for appointments/{$appointment['id']}.", 'user');

  \caldav\mark_resource_changed('appointment', $appointment['id']);

  stay_on_page();
