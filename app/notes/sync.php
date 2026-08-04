<?php
// IMAP notes syncer.

if($method != 'POST') fail("Method not allowed.", status: 405);

$id = \config\canonical_value('mail.notes-account');
if(!$id) fail("No IMAP account is enabled for notes sync.", status: 400);

$account = \store\get_imap_credentials($id)
  or fail("Account not found.", status: 404);

$client = \imap\connect($account);

try {
  $stats = \notes\reconcile($client, $account);
  \logger\info("Notes synced.", $stats);
  http_response_code(204);
}
finally {
  // A logout failure must not mask the original error.
  try { $client->logout(); } catch(\Throwable) {}
}
