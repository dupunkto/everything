<?php
  if(!isset($_GET['id'])) fail("Tag is missing.", status: 400);
  $timing = \store\get_tag($_GET['id']) or fail("Tag not found.", status: 404);
  \store\delete_tag($_GET['id']) or fail("Could not delete tag #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
?>
