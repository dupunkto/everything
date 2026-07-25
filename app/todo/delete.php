<?php

  \store\transaction(function() use ($id) {
    \store\delete_task($_GET['id']) or fail("Could not delete task.");
    \store\put_log('tasks', $_GET['id'], "Deleted task.", 'user')
      or fail("Could not create audit entry.");

    \caldav\mark_resource_deleted('task', $_GET['id']);
  });

  http_response_code(303);
  header("Location: /todo"); exit;
