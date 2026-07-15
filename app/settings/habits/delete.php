<?php

  \store\delete_habit($_GET['id']) or fail("Could not delete habit #" . $id);

  include __DIR__ . "/listing.php"; exit;
