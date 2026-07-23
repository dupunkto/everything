<?php

  \store\delete_task($_GET['id']) or fail("Could not delete task.");
  \store\insert_log('tasks', $_GET['id'], "Deleted task.", 'user')
    or fail("Could not create audit entry.");

  http_response_code(303);
  header("Location: /todo"); exit;
