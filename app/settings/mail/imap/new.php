<?php

  $form = function() { ?>
    <form class="account-editor" x-post="/settings/mail/imap/new" x-target="#account-new" x-replace="outerHTML" x-refresh="#imap-accounts">
      <div class="account-editor__header">
        <input name="name" type="text" required placeholder="Display name" aria-label="Display name" autofocus>
      </div>
      <div class="account-editor__identity">
        <label class="field">Username
          <input name="username" type="text" required placeholder="john.doe@example.com" autocomplete="off"></label>
        <label class="field">Password
          <input name="password" type="password" required autocomplete="new-password"></label>
      </div>
      <div class="account-editor__fields">
        <label class="field">Server
          <input name="hostname" type="text" required placeholder="imap.example.com"></label>
        <label class="field">Port
          <input name="port" type="number" min="1" max="65535" placeholder="993"></label>
        <label class="field">Encryption
          <?php \forms\options('ssl_mode', ['ssl' => 'SSL', 'tls' => 'STARTTLS', 'plain' => 'Plain'], 'ssl', flat: true) ?></label>
      </div>
      <div class="account-editor__actions">
        <button title="Verifies the connection before saving">Login</button>
      </div>
    </form>
  <?php };

  if(isset($_POST['name'])) {
    \imap\verify_account([
      'name' => cast_str($_POST['name']),
      'username' => cast_str($_POST['username']),
      'password' => cast_str($_POST['password']),
      'hostname' => cast_str($_POST['hostname']),
      'port' => cast_num($_POST['port']) ?? 993,
      'ssl_mode' => cast_str($_POST['ssl_mode']),
    ]);

    $id = \store\put_imap_credentials(
      cast_str($_POST['name']),
      cast_str($_POST['username']),
      cast_str($_POST['password']),
      cast_str($_POST['hostname']),
      cast_num($_POST['port']) ?? 993,
      cast_str($_POST['ssl_mode']),
    );

    \store\put_audit_log('imap_credentials', $id, "Created imap_credentials/$id.", 'user', operation: 'insert');

    ?>
    <section id="account-new" x-get="/settings/mail/imap/new" z-dismiss="escape" hidden>
      <?php $form() ?>
    </section>
    <?php exit;
  }

  $form();
