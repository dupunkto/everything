<?php

  if(isset($_POST["id"], $_POST["status"])) {
    $wish = \store\get_wish($_POST['id']);
    $fields = \core\diff($wish,
      status: $_POST['status'], comment: cast_string(@$_POST['comment']));

    \store\transaction(function() use ($fields) {
      \store\set_wish_status(
        $_POST["id"],
        $_POST["status"],
        cast_string(@$_POST['comment'])
      ) or fail("Could not update wish status.");
      
      \store\put_audit_log('wishes', $_POST['id'],
        "Updated [" . join(", ", $fields) . "] for wishes/{$_POST['id']}.", 'user')
        or fail("Could not create audit entry.");
      
      \caldav\mark_resource_changed('wish', $_POST['id']);
    });

    include "listing.php"; exit;
  } else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
