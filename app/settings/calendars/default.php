<?php

  \store\get_calendar($_GET['id'])
    or fail("Calendar not found.", status: 404);

  \store\update_config('calendar.default_calendar', $_GET['id'])
    or fail("Could not set default calendar #" . $_GET['id'] . ".");

  include __DIR__ . "/listing.php"; exit;
