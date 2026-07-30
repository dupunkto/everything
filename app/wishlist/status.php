<?php

  if(isset($_POST["id"], $_POST["status"])) {
    $wish = \store\get_wish($_POST['id']) or fail("Wish not found.", status: 404);
    $fields = \core\diff($wish,
      status: $_POST['status'], comment: cast_string(@$_POST['comment']));

    \store\set_wish_status(
      $_POST["id"],
      $_POST["status"],
      cast_string(@$_POST['comment'])
    );

    \store\put_audit_log('wishes', $_POST['id'],
      "Updated [" . join(", ", $fields) . "] for wishes/{$_POST['id']}.", 'user');

    \caldav\mark_resource_changed('wish', $_POST['id']);

    include __DIR__ . "/listing.php"; exit;
  } else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
