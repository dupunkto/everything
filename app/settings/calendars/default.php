<?php

  \store\get_calendar($_GET['id'])
    or fail("Calendar not found.", status: 404);

  \store\update_config('calendar.default_calendar', $_GET['id'])
    or fail("Could not set default calendar #" . $_GET['id'] . ".");
  \store\put_audit_log('config', 'calendar.default_calendar',
    "Set calendar.default_calendar to '{$_GET['id']}'.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
