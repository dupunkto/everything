<?php

  $ids = array_map('cast_string', $_POST['ids'] ?? []);
  $ids = array_values(array_filter($ids));

  \store\reorder_source_by_type('subscriptions', $ids);
  \store\put_audit_log('subscriptions', '*', "Updated [position] for subscriptions/*.", 'user');

  include __DIR__ . "/listing.php";
