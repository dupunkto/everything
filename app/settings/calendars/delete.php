<?php
  if(!isset($_GET['id'])) fail("Calendar is missing.", status: 400);
  $calendar = \store\get_calendar($_GET['id']) or fail("Calendar not found.", status: 404);
  \store\delete_calendar($_GET['id']) or fail("Could not delete calendar #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
?>