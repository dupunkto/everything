<?php

  if(isset($_POST["id"], $_POST["status"])) {
    \store\transaction(function() {
      \store\set_task_status(
        $_POST["id"],
        $_POST["status"],
        cast_string(@$_POST['comment'])
      ) or fail("Could not update task status.");

      \store\put_log('tasks', $_POST['id'], "Changed task status.", 'user')
        or fail("Could not create audit entry.");

      \caldav\mark_resource_changed('task', $_POST['id']);
    });

    include "listing.php"; exit;
  } else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
