<?php

  if(!isset($_GET['id'], $_GET['log_id'])) {
    fail("Could not undo status change: missing data.", status: 400);
  }

  \store\undo_task_status($_GET['id'], $_GET['log_id'])
    or fail("Could not undo status change.", status: 409);

  include __DIR__ . "/edit.php"; exit;
