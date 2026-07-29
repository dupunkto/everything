<?php

  \store\delete_habit($_GET['id']);
  \store\put_audit_log('habits', $_GET['id'], "Deleted habits/{$_GET['id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
