<?php

  \store\delete_timing($_GET['id']);
  \store\put_audit_log('timings', $_GET['id'], "Deleted timings/{$_GET['id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
