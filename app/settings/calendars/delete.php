<?php

  if(!isset($_GET['id'])) fail("Calendar is missing.", status: 400);
  $id = cast_string($_GET['id']);

  \store\delete_calendar($id) or fail("Could not delete calendar #" . $id);

  include __DIR__ . "/listing.php"; exit;
