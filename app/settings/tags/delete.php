<?php

  \store\delete_tag($_GET['id']) or fail("Could not delete tag #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
