<?php

  \store\delete_calendar($_GET['id']);

  if(CALENDAR_DEFAULT_CALENDAR == $_GET['id']) {
    \store\update_config('calendar.default_calendar', null);
    \store\put_audit_log('config', 'calendar.default_calendar',
      "Unset calendar.default_calendar.", 'user');
  }

  \store\put_audit_log('calendars', $_GET['id'], "Deleted calendars/{$_GET['id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
