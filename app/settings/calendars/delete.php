<?php

  \store\delete_calendar($_GET['id']) or fail("Could not delete calendar #" . $id);

  include __DIR__ . "/listing.php"; exit;
