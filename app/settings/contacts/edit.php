<?php

  define('ENUM_FORMATS', ['first_last', 'last_first']);
  define('ENUM_ORDERS', ['first', 'last']);

  if(isset($_POST['default-query'])) {
    \store\update_config('contacts.default-query', $_POST['default-query']);
    \store\put_audit_log('config', 'contacts', "Set contacts.default-query to '{$_POST['default-query']}'.", 'user');
  }

  if(isset($_POST['display-format'])) {
    if(!in_array($_POST['display-format'], ENUM_FORMATS))
      fail("Invalid 'display-format' parameter.", status: 400);

    \store\update_config('contacts.display-format', $_POST['display-format']);
    \store\put_audit_log('config', 'contacts', "Set contacts.display-format to '{$_POST['display-format']}'.", 'user');
  }

  if(isset($_POST['sort-order'])) {
    if(!in_array($_POST['sort-order'], ENUM_ORDERS))
      fail("Invalid 'sort-order' parameter.", status: 400);

    \store\update_config('contacts.sort-order', $_POST['sort-order']);
    \store\put_audit_log('config', 'contacts', "Set contacts.sort-order to '{$_POST['sort-order']}'.", 'user');
  }

  if(isset($_POST['prefer-nickname'])) {
    $prefer = cast_bool($_POST['prefer-nickname']) ? 'true' : 'false';
    \store\update_config('contacts.prefer-nickname', $prefer);
    \store\put_audit_log('config', 'contacts', "Set contacts.prefer-nickname to '$prefer'.", 'user');
  }


  $display = \config\fresh_value('contacts.display-format');
  $sort = \config\fresh_value('contacts.sort-order');
  $prefer_nickname = \config\fresh_value('contacts.prefer-nickname');

?>
<form class="settings-form settings-form--spaced" x-post="/settings/contacts/edit" x-on="change" x-target="#contacts-settings">
  <label>
    Default query
    <input name="default-query" type="text" placeholder="<?= esc_attr(CONTACTS_DEFAULT_QUERY) ?>"
      value="<?= esc_attr(\config\canonical_value('contacts.default-query') ?? '') ?>">
  </label>
</form>

<form class="settings-form" x-post="/settings/contacts/edit" x-on="change" x-target="#contacts-settings">
  <label>
    Display
    <?php \forms\options('display-format', [
      'first_last' => 'John Doe',
      'last_first' => 'Doe, John',
    ], $display, flat: true) ?>
  </label>
  <label>
    <input type="hidden" name="prefer-nickname" value="false">
    <input type="checkbox" name="prefer-nickname" value="true" <?= $prefer_nickname ? 'checked' : '' ?>>
    Prefer nickname over display name
  </label>
  <label>
    Sort by
    <?php \forms\options('sort-order', [
      'first' => 'First name',
      'last' => 'Last name',
    ], $sort, flat: true) ?>
  </label>
</form>
