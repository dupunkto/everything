<?php

  \store\set_task_urgent($_POST['id'], cast_boolean($_POST['urgent']))
    or fail("Could not update task urgency.");
  \store\insert_log('tasks', $_POST['id'], "Changed task urgency.", 'user')
    or fail("Could not create audit entry.");

  include "listing.php"; exit;
