<?php

  if(isset($_POST["id"], $_POST["color"], $_POST["title"])) {
    $calendar = \store\get_calendar($_POST['id']) or fail("Calendar not found.", status: 404);
    $fields = \core\diff($calendar,
      title: cast_str($_POST['title']), subtitle: cast_str($_POST['subtitle']), color: cast_color($_POST['color']));

    \store\update_calendar($_POST['id'], cast_str($_POST['title']), cast_str($_POST['subtitle']), cast_color($_POST['color']));
    \store\put_audit_log('calendars', $_POST['id'], "Updated [" . join(", ", $fields) . "] for calendars/{$_POST['id']}.", 'user');

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
