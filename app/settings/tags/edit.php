<?php
  if(allset($_POST, ["id", "color", "label", "parent"])) {
    \store\update_tag($_POST['id'], $_POST['label'], $_POST['color'], $_POST['parent'])
      or fail("Could not save tag #" . $_POST['id'] . " with label '" . $_POST['label'] . "', color " . $_POST['color'] . " and parent #" . $_POST['parent'] . ".");

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
?>