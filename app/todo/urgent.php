<?php

  $task = \store\get_task($_POST['id']) or fail("Task not found.", status: 404);
  $fields = \core\diff($task, urgent: cast_boolean($_POST['urgent']));

  \store\set_task_urgent($_POST['id'], cast_boolean($_POST['urgent']));
  \store\put_audit_log('tasks', $_POST['id'],
    "Updated [" . join(", ", $fields) . "] for tasks/{$_POST['id']}.", 'user');

  \caldav\mark_resource_changed('task', $_POST['id']);

  include "listing.php"; exit;
