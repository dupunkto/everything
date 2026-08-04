<?php

  \store\get_imap_credentials($_GET['id'])
    or fail("Account not found.", status: 404);

  \store\delete_imap_credentials($_GET['id']);

  foreach(['mail.default-account', 'mail.notes-account'] as $key) {
    if(\config\canonical_value($key) == $_GET['id']) {
      \store\update_config($key, null);
      \store\put_audit_log('config', $key, "Unset $key.", 'user');
    }
  }

  \store\put_audit_log('imap_credentials', $_GET['id'],
    "Deleted imap_credentials/{$_GET['id']}.", 'user', operation: 'delete');

  include __DIR__ . "/listing.php"; exit;
