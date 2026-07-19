<?php

  \store\set_task_urgent($_POST['id'], cast_boolean($_POST['urgent']))
    or fail("Could not update task urgency.");

  include "listing.php"; exit;
