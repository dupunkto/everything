<?php

  \store\delete_calendar($_GET['id']) or fail("Could not delete calendar #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
