<?php

  $id = \store\put_calendar("Untitled calendar", null, cast_color("#efefef"));
  \store\put_audit_log('calendars', $id, "Created calendars/$id.", 'user', operation: 'insert');

  include __DIR__ . "/listing.php"; exit;

