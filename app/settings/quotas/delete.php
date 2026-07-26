<?php

  \store\delete_quota($_GET['tag_id']) or fail("Could not delete tracker quota.");
  \store\put_audit_log('quotas', $_GET['tag_id'], "Deleted quotas/{$_GET['tag_id']}.", 'user', operation: 'delete')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
