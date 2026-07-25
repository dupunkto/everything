<?php

  \store\delete_address($_GET['id']) or fail("Could not delete address.");
  \store\put_audit_log('addresses', $_GET['id'], "Deleted addresses/{$_GET['id']}.", 'user', operation: 'delete')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
