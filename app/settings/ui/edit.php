<?php

  define('ENUM_PANEL_POSITION', ['left', 'right']);

  if(isset($_POST['panel-position'])) {
    if(!in_array($_POST['panel-position'], ENUM_PANEL_POSITION))
      fail("Invalid 'panel-position' parameter.", status: 400);

    \store\update_config('ui.panel-position', $_POST['panel-position'])
      or fail("Could not update panel position.");

    // We need to do a full reload for the panel to change place.
    http_response_code(303);
    header("Location: /settings/ui");
    exit;
  }

?>
<form class="settings-form" x-post="/settings/ui/edit" x-on="change" x-target="#ui-settings">
  <label>
    Panel position
    <?php \forms\options('panel-position', [
      'left' => 'Left',
      'right' => 'Right',
    ], UI_PANEL_POSITION, flat: true) ?>
  </label>
</form>
