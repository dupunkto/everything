<?php

  if(!isset($_GET['id'], $_GET['log_id'])) {
    fail("Could not undo status change: missing data.", status: 400);
  }

  \store\transaction(function() {
    \store\undo_task_status($_GET['id'], $_GET['log_id'])
      or fail("Could not undo status change.", status: 409);
    \store\put_audit_log('tasks', $_GET['id'], "Updated [status] for tasks/{$_GET['id']}.", 'user')
      or fail("Could not create audit entry.");

    \caldav\mark_resource_changed('task', $_GET['id']);
  });

  include __DIR__ . "/edit.php"; exit;
