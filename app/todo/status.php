<?php

  if(isset($_POST["id"], $_POST["status"])) {
    $task = \store\get_task($_POST['id']) or fail("Task not found.", status: 404);
    $fields = \core\diff($task,
      status: $_POST['status'], comment: cast_string(@$_POST['comment']));

    \store\set_task_status(
      $_POST["id"],
      $_POST["status"],
      cast_string(@$_POST['comment'])
    );

    \store\put_audit_log('tasks', $_POST['id'],
      "Updated [" . join(", ", $fields) . "] for tasks/{$_POST['id']}.", 'user');

    \caldav\mark_resource_changed('task', $_POST['id']);

    include "listing.php"; exit;
  } else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
