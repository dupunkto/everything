<?php

  \store\transaction(function() use ($id) {
    \store\delete_task($_GET['id']) or fail("Could not delete task.");
    \store\put_audit_log('tasks', $_GET['id'], "Deleted tasks/{$_GET['id']}.", 'user', operation: 'delete')
      or fail("Could not create audit entry.");

    \caldav\mark_resource_deleted('task', $_GET['id']);
  });

  http_response_code(303);
  header("Location: /todo"); exit;
