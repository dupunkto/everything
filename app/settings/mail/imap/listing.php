<?php
  $default_account = \config\fresh_value('mail.default-account')
    ?? @\store\get_first_imap_credentials()['id'];

  $notes_account = \config\canonical_value('mail.notes-account');
?>

<ul class="settings-listing">
  <?php foreach(\store\list_imap_credentials() as $account): ?>
    <?php
      $is_default = $account['id'] == $default_account;
      $is_notes = $account['id'] == $notes_account;
    ?>
    <li class="account-editor">
      <form x-post="/settings/mail/imap/edit" x-target="#imap-accounts">
        <div class="account-editor__header">
          <input name="id" type="hidden" value="<?= esc_attr($account['id']) ?>">
          <input name="name" type="text" required value="<?= esc_attr($account['name']) ?>" placeholder="Display name" aria-label="Display name">
          <button class="settings-editor__icon-button settings-editor__icon-button--square" type="button" title="Make default account" x-post="/settings/mail/imap/default?id=<?= esc_attr($account['id']) ?>" x-target="#imap-accounts"><i class="fa-<?= $is_default ? 'solid' : 'regular' ?> fa-star"></i></button>
          <button class="settings-editor__icon-button settings-editor__icon-button--square" type="button" title="<?= $is_notes ? 'Disable notes sync' : 'Sync notes with this account' ?>" x-post="/settings/mail/imap/notes?id=<?= esc_attr($account['id']) ?>" x-target="#imap-accounts"><i class="fa-<?= $is_notes ? 'solid' : 'regular' ?> fa-notebook"></i></button>
          <button type="button" x-delete="/settings/mail/imap/delete?id=<?= esc_attr($account['id']) ?>" x-target="#imap-accounts" z-confirm="Delete this account?">&times;</button>
        </div>
        <div class="account-editor__identity">
          <label class="field">Username
            <input name="username" type="text" required placeholder="john.doe" value="<?= esc_attr($account['username']) ?>" autocomplete="off"></label>
          <label class="field">Password
            <input name="password" type="password" value="" placeholder="leave empty to keep current passphrase" autocomplete="new-password"></label>
        </div>
        <div class="account-editor__fields">
          <label class="field">Server
            <input name="hostname" type="text" required value="<?= esc_attr($account['hostname']) ?>"></label>
          <label class="field">Port
            <input name="port" type="number" min="1" max="65535" placeholder="993" value="<?= esc_attr($account['port']) ?>"></label>
          <label class="field">Encryption
            <?php \forms\options('ssl_mode', ['ssl' => 'SSL', 'tls' => 'STARTTLS', 'plain' => 'Plain'], $account['ssl_mode'], flat: true) ?></label>
        </div>
        <div class="account-editor__actions">
          <button title="Verifies the connection before saving">Save</button>
        </div>
      </form>
    </li>
  <?php endforeach ?>
</ul>
