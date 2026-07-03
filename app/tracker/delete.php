<?php

  if(!isset($_GET['id'])) fail("Timing is missing.", status: 400);
  $timing = \store\get_timing($_GET['id']) or fail("Timing not found.", status: 404);
  \store\delete_timing($_GET['id']) or fail("Could not delete timing #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
