<?php
  // Drag-to-create target for the week view.

  DEFAULT_CALENDAR or fail("No calendar to create appointments on.", status: 409);

  $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
  $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

  $id = \store\create_appointment(DEFAULT_CALENDAR, "New event", null, $starts_at, $ends_at)
    or fail("Could not create appointment.");

  // Return the ID. The frontend will use this to open an edit modal.
  header("Content-Type: text/plain");
  echo $id;
