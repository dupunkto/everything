<?php

  $ids = array_map('cast_string', $_POST['ids'] ?? []);
  $ids = array_values(array_filter($ids));

  \store\reorder_source_by_type('subscriptions', $ids) or fail("Could not reorder subscriptions.");

  include __DIR__ . "/listing.php";
