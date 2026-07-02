<?php
  if(!isset($_GET['id'])) fail("Subscription is missing.", status: 400);
  $sub = \store\get_subscription($_GET['id']) or fail("Subscription not found.", status: 404);
  \store\delete_subscription($_GET['id']) or fail("Could not delete subscription #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
?>