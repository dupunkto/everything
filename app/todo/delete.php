<?php

  \store\delete_task($_GET['id']);
  \store\put_audit_log('tasks', $_GET['id'], "Deleted tasks/{$_GET['id']}.", 'user', operation: 'delete');

  \caldav\mark_resource_deleted('task', $_GET['id']);

  http_response_code(303);
  header("Location: /todo"); exit;
