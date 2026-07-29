<?php

  \store\delete_tag($_GET['id']);
  \store\put_audit_log('tags', $_GET['id'], "Deleted tags/{$_GET['id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
