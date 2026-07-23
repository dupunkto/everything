<?php

  $ids = array_map('cast_string', $_POST['ids'] ?? []);
  $ids = array_values(array_filter($ids));

  \store\reorder_source_by_type('subscriptions', $ids) or fail("Could not reorder subscriptions.");
  \store\insert_log('subscriptions', '*', "Reordered subscriptions.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php";
