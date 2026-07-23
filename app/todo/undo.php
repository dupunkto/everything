<?php

  if(!isset($_GET['id'], $_GET['log_id'])) {
    fail("Could not undo status change: missing data.", status: 400);
  }

  \store\undo_task_status($_GET['id'], $_GET['log_id'])
    or fail("Could not undo status change.", status: 409);
  \store\insert_log('tasks', $_GET['id'], "Undid task status change.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/edit.php"; exit;
