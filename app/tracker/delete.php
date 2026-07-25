<?php

  \store\delete_timing($_GET['id']) or fail("Could not delete timing #" . $_GET['id']);
  \store\put_audit_log('timings', $_GET['id'], "Deleted timings/{$_GET['id']}.", 'user', operation: 'delete')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
