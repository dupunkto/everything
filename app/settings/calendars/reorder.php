<?php

  $ids = array_map('cast_string', $_POST['ids'] ?? []);
  $ids = array_values(array_filter($ids));

  \store\reorder_source_by_type('calendars', $ids);
  \store\put_audit_log('calendars', '*', "Updated [position] for calendars/*.", 'user');

  include __DIR__ . "/listing.php";
