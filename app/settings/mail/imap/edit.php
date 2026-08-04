<?php

  if(isset($_POST['id'], $_POST['name'], $_POST['username'], $_POST['hostname'], $_POST['port'], $_POST['ssl_mode'])) {
    $existing = \store\get_imap_credentials($_POST['id']) 
      or fail("Account not found.", status: 404);

    $password = $_POST['password'] ?? "";
    if($password === "") $password = $existing['password'];

    $account = [
      'name' => cast_str($_POST['name']),
      'username' => cast_str($_POST['username']),
      'password' => $password,
      'hostname' => cast_str($_POST['hostname']),
      'port' => cast_num($_POST['port']) ?? 993,
      'ssl_mode' => cast_str($_POST['ssl_mode']),
    ];

    \imap\verify_account($account);

    $fields = \core\diff($existing, ...array_diff_key($account, ['password' => null]));
    if($password != $existing['password']) $fields[] = 'password';

    \store\update_imap_credentials(
      $_POST['id'],
      cast_str($_POST['name']),
      cast_str($_POST['username']),
      $password,
      cast_str($_POST['hostname']),
      cast_num($_POST['port']) ?? 993,
      cast_str($_POST['ssl_mode']),
    );

    \store\put_audit_log('imap_credentials', $existing['id'],
      "Updated [" . join(", ", $fields) . "] for imap_credentials/{$existing['id']}.", 'user');

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
