<?php

  if(isset($_POST["id"], $_POST["status"])) {
    $task = \store\get_task($_POST['id']);
    $fields = \core\diff($task,
      status: $_POST['status'], comment: cast_string(@$_POST['comment']));

    \store\transaction(function() use ($fields) {
      \store\set_task_status(
        $_POST["id"],
        $_POST["status"],
        cast_string(@$_POST['comment'])
      ) or fail("Could not update task status.");

      \store\put_audit_log('tasks', $_POST['id'],
        "Updated [" . join(", ", $fields) . "] for tasks/{$_POST['id']}.", 'user')
        or fail("Could not create audit entry.");

      \caldav\mark_resource_changed('task', $_POST['id']);
    });

    include "listing.php"; exit;
  } else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
