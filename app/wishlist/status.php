<?php

  if(isset($_POST["id"], $_POST["status"])) {
    \store\set_wish_status(
      $_POST["id"],
      $_POST["status"],
      cast_string(@$_POST['comment'])
    ) or fail("Could not update wish status.");
    \store\insert_log('wishes', $_POST['id'], "Changed wish status.", 'user')
      or fail("Could not create audit entry.");

    include "listing.php"; exit;
  } else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
