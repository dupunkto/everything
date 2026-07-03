<?php

  if(allset($_POST, ["id", "status"])) {
    \store\set_task_status(
      $_POST["id"],
      $_POST["status"],
      @$_POST["comment"]
    ) or fail("Could not update task status.");

    include "listing.php"; exit;
  } else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
