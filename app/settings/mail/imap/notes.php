<?php
  // Toggles which account syncs notes.

  \store\get_imap_credentials($_GET['id'])
    or fail("Account not found.", status: 404);

  if(\config\canonical_value('mail.notes-account') == $_GET['id']) {
    \store\update_config('mail.notes-account', null);
    \store\put_audit_log('config', 'mail.notes-account',
      "Unset mail.notes-account.", 'user');
  }
  else {
    \store\update_config('mail.notes-account', $_GET['id']);
    \store\put_audit_log('config', 'mail.notes-account',
      "Set mail.notes-account to '{$_GET['id']}'.", 'user');
  }

  include __DIR__ . "/listing.php"; exit;
