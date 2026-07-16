<?php

  $ids = array_map('cast_int', $_POST['ids'] ?? []);
  $ids = array_values(array_filter($ids));

  \store\reorder_tags($ids) or fail("Could not reorder tags.");

  include __DIR__ . "/listing.php";
