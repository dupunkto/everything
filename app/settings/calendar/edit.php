<?php

  define('ENUM_UI_HABITS_POSITION', ['top', 'bottom']);

  if(isset($_POST['redacted-titles'])) {
    $redacted_titles = cast_bool($_POST['redacted-titles']) ? 'true' : 'false';
    \store\update_config('calendar.redacted-titles', $redacted_titles);
    \store\put_audit_log('config', 'calendar', "Set calendar.redacted-titles to '$redacted_titles'.", 'user');
  }

  if(isset($_POST['habits-position'])) {
    if(!in_array($_POST['habits-position'], ENUM_UI_HABITS_POSITION))
      fail("Invalid 'habits-position' parameter.", status: 400);

    \store\update_config('ui.habits-position', $_POST['habits-position']);
    \store\put_audit_log('config', 'ui', "Set ui.habits-position to '{$_POST['habits-position']}'.", 'user');
  }

  $redacted_titles = \config\fresh_value('calendar.redacted-titles');

?>
<form class="settings-form" x-post="/settings/calendar/edit" x-on="change" x-target="#calendar-settings">
  <label>
    <input type="hidden" name="redacted-titles" value="false">
    <input type="checkbox" name="redacted-titles" value="true" <?= $redacted_titles ? 'checked' : '' ?>>
    Redacted CalDAV calendars expose Calendar title
  </label>

  <label>
    Habits position
    <?php \forms\options('habits-position', [
      'top' => 'Top',
      'bottom' => 'Bottom',
    ], \config\fresh_value('ui.habits-position'), flat: true) ?>
  </label>
</form>
