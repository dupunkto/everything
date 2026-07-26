<?php

  $formats = ['first_last', 'last_first'];
  $orders = ['first', 'last'];

  if(isset($_POST['display-format'])) {
    if(!in_array($_POST['display-format'], $formats))
      fail("Invalid 'display-format' parameter.", status: 400);

    \store\update_config('contacts.display-format', $_POST['display-format'])
      or fail("Could not update display format.");
    \store\put_audit_log('config', 'contacts', "Set contacts.display-format to '{$_POST['display-format']}'.", 'user')
      or fail("Could not create audit entry.");
  }

  if(isset($_POST['sort-order'])) {
    if(!in_array($_POST['sort-order'], $orders))
      fail("Invalid 'sort-order' parameter.", status: 400);

    \store\update_config('contacts.sort-order', $_POST['sort-order'])
      or fail("Could not update sort order.");
    \store\put_audit_log('config', 'contacts', "Set contacts.sort-order to '{$_POST['sort-order']}'.", 'user')
      or fail("Could not create audit entry.");
  }


  $display = \config\fresh_value('contacts.display-format');
  $sort = \config\fresh_value('contacts.sort-order');

?>
<form class="settings-form" x-post="/settings/contacts/edit" x-on="change" x-target="#contacts-settings">
  <label>
    Display
    <?php \forms\options('display-format', [
      'first_last' => 'John Doe',
      'last_first' => 'Doe, John',
    ], $display, flat: true) ?>
  </label>
  <label>
    Sort by
    <?php \forms\options('sort-order', [
      'first' => 'First name',
      'last' => 'Last name',
    ], $sort, flat: true) ?>
  </label>
</form>
