<?php

  \store\delete_quota($_GET['tag_id']);
  \store\put_audit_log('quotas', $_GET['tag_id'], "Deleted quotas/{$_GET['tag_id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
