<?php

  \store\delete_address($_GET['id']);
  \store\put_audit_log('addresses', $_GET['id'], "Deleted addresses/{$_GET['id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
