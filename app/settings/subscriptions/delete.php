<?php

  \store\delete_subscription($_GET['id'])
    or fail("Could not delete subscription #" . $_GET['id']);
  \store\put_audit_log('subscriptions', $_GET['id'], "Deleted subscriptions/{$_GET['id']}.", 'user', operation: 'delete')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
