<?php

  if(isset($_POST["id"], $_POST["color"], $_POST["title"])) {
    $calendar = \store\get_calendar($_POST['id']);
    $fields = \core\diff($calendar,
      title: cast_string($_POST['title']), subtitle: cast_string($_POST['subtitle']), color: cast_color($_POST['color']));

    \store\update_calendar($_POST['id'], cast_string($_POST['title']), cast_string($_POST['subtitle']), cast_color($_POST['color']))
      or fail("Could not save calendar #" . $_POST['id'] . ".");
    \store\put_audit_log('calendars', $_POST['id'], "Updated [" . join(", ", $fields) . "] for calendars/{$_POST['id']}.", 'user')
      or fail("Could not create audit entry.");

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
