<?php

  \store\delete_subscription($_GET['id']);
  \store\put_audit_log('subscriptions', $_GET['id'], "Deleted subscriptions/{$_GET['id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
