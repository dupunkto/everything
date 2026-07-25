<?php

  $task = \store\get_task($_POST['id']);
  $fields = \core\diff($task, urgent: cast_boolean($_POST['urgent']));

  \store\transaction(function() use ($fields) {
    \store\set_task_urgent($_POST['id'], cast_boolean($_POST['urgent']))
      or fail("Could not update task urgency.");
    \store\put_audit_log('tasks', $_POST['id'],
      "Updated [" . join(", ", $fields) . "] for tasks/{$_POST['id']}.", 'user')
      or fail("Could not create audit entry.");

    \caldav\mark_resource_changed('task', $_POST['id']);
  });

  include "listing.php"; exit;
