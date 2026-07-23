<?php

  $ids = array_map('cast_int', $_POST['ids'] ?? []);
  $ids = array_values(array_filter($ids));

  \store\reorder_tags($ids) or fail("Could not reorder tags.");
  \store\insert_log('tags', '*', "Reordered tags.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php";
