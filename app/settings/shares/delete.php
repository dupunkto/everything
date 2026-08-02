<?php

  $share = \store\get_share($_GET['id']) or fail("Share not found.", status: 404);
  \store\delete_share($share['id']);
  \store\put_audit_log('shares', $share['id'], "Deleted shares/{$share['id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
