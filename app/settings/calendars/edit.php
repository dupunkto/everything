<?php
  if(allset($_POST, ["id", "color", "title"])) {
    \store\update_calendar($_POST['id'], $_POST['title'], $_POST['subtitle'] ?? '', $_POST['color'])
      or fail("Could not save calendar #" . $_POST['id'] . ".");

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
?>