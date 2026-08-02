<?php

  \store\get_calendar($_GET['id'])
    or fail("Calendar not found.", status: 404);

  \store\update_config('calendar.default-calendar', $_GET['id']);
  \store\put_audit_log('config', 'calendar.default-calendar',
    "Set calendar.default-calendar to '{$_GET['id']}'.", 'user');

  include __DIR__ . "/listing.php"; exit;
