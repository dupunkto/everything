<?php
// IMAP notes syncer.

if($method != 'POST') fail("Method not allowed.", status: 405);

$id = \config\canonical_value('mail.notes-account');
if(!$id) fail("No IMAP account is enabled for notes sync.", status: 400);

$account = \store\get_imap_credentials($id)
  or fail("Account not found.", status: 404);

set_time_limit(0); // it might take a long time

$client = \imap\connect($account);

try {
  $plan = \notes\plan($client, $account);
  \notes\append($client, $plan);

  begin_request();
  $stats = \notes\save($plan);

  if($plan['expunge']) {
    bracket_request([
      'committed' => fn() => \notes\expunge($account, $plan),
    ]);
  }

  \logger\info("Notes synced.", $stats);
  http_response_code(204);
}
finally {
  // A logout failure must not mask the original error.
  try { $client->logout(); } catch(\Throwable) {}
}
