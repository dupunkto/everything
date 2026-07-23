<?php

  $id = \store\create_calendar("Untitled calendar", null, cast_color("#efefef")) or fail("Could not create new calendar.");
  \store\insert_log('calendars', $id, "Created calendar.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;

