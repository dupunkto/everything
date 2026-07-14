<?php

  \store\delete_timing($_GET['id']) or fail("Could not delete timing #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
