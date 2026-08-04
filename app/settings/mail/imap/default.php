<?php

  \store\get_imap_credentials($_GET['id'])
    or fail("Account not found.", status: 404);

  \store\update_config('mail.default-account', $_GET['id']);
  \store\put_audit_log('config', 'mail.default-account',
    "Set mail.default-account to '{$_GET['id']}'.", 'user');

  include __DIR__ . "/listing.php"; exit;
