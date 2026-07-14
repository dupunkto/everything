<?php

  if(isset($_POST["id"], $_POST["status"])) {
    \store\set_wish_status(
      $_POST["id"],
      $_POST["status"],
      $_POST["comment"]
    ) or fail("Could not update wish status.");

    include "listing.php"; exit;
  } else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
