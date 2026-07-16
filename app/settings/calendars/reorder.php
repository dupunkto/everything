<?php

  $ids = array_map('cast_string', $_POST['ids'] ?? []);
  $ids = array_values(array_filter($ids));

  \store\reorder_source_by_type('calendars', $ids) or fail("Could not reorder calendars.");

  include __DIR__ . "/listing.php";
