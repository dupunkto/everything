<?php

  $id = \store\put_calendar("Untitled calendar", null, cast_color("#efefef")) or fail("Could not create new calendar.");
  \store\put_audit_log('calendars', $id, "Created calendars/$id.", 'user', operation: 'insert')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;

