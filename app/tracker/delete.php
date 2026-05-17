<?php
  if(!isset($_GET['id'])) fail("Timing is missing.");
  $timing = \store\get_timing($_GET['id']) or fail("Timing not found.");
  \store\delete_timing($_GET['id']) or fail("Couldn't delete timing #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
?>
