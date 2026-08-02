<?php

  $ids = array_map('cast_num', $_POST['ids'] ?? []);
  $ids = array_values(array_filter($ids));

  \store\reorder_tags($ids);
  \store\put_audit_log('tags', '*', "Updated [position] for tags/*.", 'user');

  include __DIR__ . "/listing.php";
