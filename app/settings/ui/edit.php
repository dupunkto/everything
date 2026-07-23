<?php

  define('ENUM_UI_POSITION', ['left', 'right']);
  define('ENUM_UI_HABITS_POSITION', ['top', 'bottom']);

  if(isset($_POST['panel-position'])) {
    if(!in_array($_POST['panel-position'], ENUM_UI_POSITION))
      fail("Invalid 'panel-position' parameter.", status: 400);

    \store\update_config('ui.panel-position', $_POST['panel-position'])
      or fail("Could not update panel position.");
    \store\insert_log('config', 'ui', "Updated UI settings.", 'user')
      or fail("Could not create audit entry.");

    // We need to do a full reload for the panel to change place.
    http_response_code(303);
    header("Location: /settings/ui");
    exit;
  }

  if(isset($_POST['sidebar-position'])) {
    if(!in_array($_POST['sidebar-position'], ENUM_UI_POSITION))
      fail("Invalid 'sidebar-position' parameter.", status: 400);

    \store\update_config('ui.sidebar-position', $_POST['sidebar-position'])
      or fail("Could not update sidebar position.");
  }

  if(isset($_POST['habits-position'])) {
    if(!in_array($_POST['habits-position'], ENUM_UI_HABITS_POSITION))
      fail("Invalid 'habits-position' parameter.", status: 400);

    \store\update_config('ui.habits-position', $_POST['habits-position'])
      or fail("Could not update habits position.");
  }

  if($_POST) {
    \store\insert_log('config', 'ui', "Updated UI settings.", 'user')
      or fail("Could not create audit entry.");
  }

?>
<form class="settings-form settings-form--spaced" x-post="/settings/ui/edit" x-on="change" x-target="#ui-settings">
  <label>
    Panel position
    <?php \forms\options('panel-position', [
      'left' => 'Left',
      'right' => 'Right',
    ], UI_PANEL_POSITION, flat: true) ?>
  </label>
</form>

<!-- This is a separate form because the first form needs to do a full page reload, and these
     settings do NOT need to do that (and a full reload is disruptive UX imo). Having them in
     the same form would submit panel-position with changes to sidebar-position, and perform
     a full reload (and worse yet, do the redirect *before* we even reach the update handler
     for the sidebar-position). -->
<form class="settings-form" x-post="/settings/ui/edit" x-on="change" x-target="#ui-settings">
  <label>
    Sidebar position
    <?php \forms\options('sidebar-position', [
      'left' => 'Left',
      'right' => 'Right',
    ], \config\fresh_value('ui.sidebar-position'), flat: true) ?>
  </label>

  <label>
    Habits position
    <?php \forms\options('habits-position', [
      'top' => 'Top',
      'bottom' => 'Bottom',
    ], \config\fresh_value('ui.habits-position'), flat: true) ?>
  </label>
</form>
