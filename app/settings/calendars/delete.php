<?php

  \store\delete_calendar($_GET['id']) or fail("Could not delete calendar #" . $id);

  if(CALENDAR_DEFAULT_CALENDAR == $_GET['id']) {
    \store\update_config('calendar.default_calendar', null)
      or fail("Could not unset default calendar.");
  }

  include __DIR__ . "/listing.php"; exit;
