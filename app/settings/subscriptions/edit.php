<?php

  if(isset($_POST["id"], $_POST["color"], $_POST["title"], $_POST["url"])) {
    \store\update_subscription($_POST['id'], $_POST['title'], $_POST['subtitle'] ?? '', $_POST['url'], $_POST['color'])
      or fail("Could not save subscription #" . $_POST['id'] . ".");

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
