<?php

  if(isset($_POST["id"], $_POST["color"], $_POST["title"])) {
    \store\update_calendar($_POST['id'], cast_string($_POST['title']), cast_string($_POST['subtitle']), cast_color($_POST['color']))
      or fail("Could not save calendar #" . $_POST['id'] . ".");
    \store\put_log('calendars', $_POST['id'], "Updated calendar.", 'user')
      or fail("Could not create audit entry.");

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
