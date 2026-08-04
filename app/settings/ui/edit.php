<?php

  define('ENUM_UI_POSITION', ['left', 'right']);
  define('ENUM_UI_HABITS_POSITION', ['top', 'bottom']);
  define('ENUM_UI_BORDER_RADIUS', ['square', 'subtle', 'rounded']);
  define('ENUM_UI_APPLICATION', array_keys(application_options()));
  define('ENUM_UI_INSERT_APPLICATION', array_keys(application_options(insertable: true)));

  if(isset($_POST['default-application'])) {
    if(!in_array($_POST['default-application'], ENUM_UI_APPLICATION))
      fail("Invalid 'default-application' parameter.", status: 400);

    \store\update_config('ui.default-application', $_POST['default-application']);
    \store\put_audit_log('config', 'ui', "Set ui.default-application to '{$_POST['default-application']}'.", 'user');

    see_other("/settings/ui");
  }

  if(isset($_POST['insert-application'])) {
    if(!in_array($_POST['insert-application'], ENUM_UI_INSERT_APPLICATION))
      fail("Invalid 'insert-application' parameter.", status: 400);

    \store\update_config('ui.insert-application', $_POST['insert-application']);
    \store\put_audit_log('config', 'ui', "Set ui.insert-application to '{$_POST['insert-application']}'.", 'user');

    see_other("/settings/ui");
  }

  if(isset($_POST['panel-position'])) {
    if(!in_array($_POST['panel-position'], ENUM_UI_POSITION))
      fail("Invalid 'panel-position' parameter.", status: 400);

    \store\update_config('ui.panel-position', $_POST['panel-position']);
    \store\put_audit_log('config', 'ui', "Set ui.panel-position to '{$_POST['panel-position']}'.", 'user');

    // We need to do a full reload for the panel to change place.
    see_other("/settings/ui");
  }

  if(isset($_POST['border-radius'])) {
    if(!in_array($_POST['border-radius'], ENUM_UI_BORDER_RADIUS))
      fail("Invalid 'border-radius' parameter.", status: 400);

    \store\update_config('ui.border-radius', $_POST['border-radius']);
    \store\put_audit_log('config', 'ui', "Set ui.border-radius to '{$_POST['border-radius']}'.", 'user');

    see_other("/settings/ui");
  }

  if(isset($_POST['sidebar-position'])) {
    if(!in_array($_POST['sidebar-position'], ENUM_UI_POSITION))
      fail("Invalid 'sidebar-position' parameter.", status: 400);

    \store\update_config('ui.sidebar-position', $_POST['sidebar-position']);
    \store\put_audit_log('config', 'ui', "Set ui.sidebar-position to '{$_POST['sidebar-position']}'.", 'user');
  }

  if(isset($_POST['habits-position'])) {
    if(!in_array($_POST['habits-position'], ENUM_UI_HABITS_POSITION))
      fail("Invalid 'habits-position' parameter.", status: 400);

    \store\update_config('ui.habits-position', $_POST['habits-position']);
    \store\put_audit_log('config', 'ui', "Set ui.habits-position to '{$_POST['habits-position']}'.", 'user');
  }

?>
<form class="settings-form settings-form--spaced" x-post="/settings/ui/edit" x-on="change" x-target="#ui-settings">
  <label>
    Default application
    <?php \forms\options('default-application', application_options(), \config\fresh_value('ui.default-application'), flat: true) ?>
  </label>
</form>

<form class="settings-form settings-form--spaced" x-post="/settings/ui/edit" x-on="change" x-target="#ui-settings">
  <label>
    Insert application
    <?php \forms\options('insert-application', application_options(insertable: true), \config\fresh_value('ui.insert-application'), flat: true) ?>
  </label>
</form>

<form class="settings-form settings-form--spaced" x-post="/settings/ui/edit" x-on="change" x-target="#ui-settings">
  <label>
    Panel position
    <?php \forms\options('panel-position', [
      'left' => 'Left',
      'right' => 'Right',
    ], UI_PANEL_POSITION, flat: true) ?>
  </label>
</form>

<form class="settings-form settings-form--spaced" x-post="/settings/ui/edit" x-on="change" x-target="#ui-settings">
  <label>
    Corner style
    <?php \forms\options('border-radius', [
      'square' => 'Square',
      'subtle' => 'Subtle',
      'rounded' => 'Rounded',
    ], UI_BORDER_RADIUS, flat: true) ?>
  </label>
</form>

<!-- This is a separate form because the panel position form needs to do a full page reload,
     and these settings do NOT need to do that (and a full reload is disruptive UX imo).
     Having them in the same form would submit panel-position with changes to sidebar-position,
     and perform a full reload (and worse yet, do the redirect *before* we even reach the update
     handler for the sidebar-position). -->
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
