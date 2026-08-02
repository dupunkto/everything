<?php

  $share = \store\get_share($_GET['id']) or fail("Share not found.", status: 404);
  \store\update_share_token($share['id'], bin2hex(random_bytes(32)));
  \store\put_audit_log('shares', $share['id'], "Cycled token for shares/{$share['id']}.", 'user');

  include __DIR__ . "/listing.php"; exit;
