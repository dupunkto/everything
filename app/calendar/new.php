<?php
  // Drag-to-create target for the week view.

  CALENDAR_DEFAULT_CALENDAR or fail("No calendar to create appointments on.", status: 409);

  $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

  $id = \store\create_calendar_appointment(CALENDAR_DEFAULT_CALENDAR, "New event", null, $starts_at, $ends_at,
    all_day: !empty($_POST['all_day']))
    or fail("Could not create appointment.");

  header("Content-Type: text/plain");
  echo $id; // Return the ID. The frontend will use this to open an edit modal.
