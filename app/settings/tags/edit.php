<?php
  if(isset($_POST["id"], $_POST["label"], $_POST["parent"])) {
    \store\update_tag($_POST['id'], cast_string($_POST['label']), cast_color(@$_POST['color']), cast_int($_POST['parent']))
      or fail("Could not save tag #" . $_POST['id'] . " with label '" . $_POST['label'] . "' and parent #" . $_POST['parent'] . ".");
    \store\put_log('tags', $_POST['id'], "Updated tag.", 'user')
      or fail("Could not create audit entry.");

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
