<?php

  if(isset($_POST['layout'])) {
    if(!in_array($_POST['layout'], ['masonry', 'listing']))
      fail("Invalid 'layout' parameter.", status: 400);

    \store\update_config('notes.layout', $_POST['layout']);
    \store\put_audit_log('config', 'notes', "Set notes.layout to '{$_POST['layout']}'.", 'user');
  }

?>
<form class="settings-form settings-form--spaced" x-post="/settings/notes/edit" x-on="change" x-target="#notes-settings">
  <label>
    Layout
    <?php \forms\options('layout', [
      'masonry' => 'Masonry',
      'listing' => 'Listing',
    ], \config\fresh_value('notes.layout'), flat: true) ?>
  </label>
</form>
