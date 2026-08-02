<?php

  if(isset($_POST['default-query'])) {
    \store\update_config('todo.default-query', $_POST['default-query']);
    \store\put_audit_log('config', 'todo', "Set todo.default-query to '{$_POST['default-query']}'.", 'user');
  }

  if(isset($_POST['recurrence_horizon'])) {
    \store\update_config('todo.recurrence-horizon', cast_num($_POST['recurrence_horizon']));
    \store\put_audit_log('config', 'todo', "Set todo.recurrence-horizon to '{$_POST['recurrence_horizon']}'.", 'user');
  }

  if(isset($_POST['layout'])) {
    if(!in_array($_POST['layout'], ['masonry', 'horizontal']))
      fail("Invalid 'layout' parameter.", status: 400);

    \store\update_config('todo.layout', $_POST['layout']);
    \store\put_audit_log('config', 'todo', "Set todo.layout to '{$_POST['layout']}'.", 'user');
  }

  if(isset($_POST['display'])) {
    if(!in_array($_POST['display'], ['tag', 'status']))
      fail("Invalid 'display' parameter.", status: 400);

    \store\update_config('todo.display', $_POST['display']);
    \store\put_audit_log('config', 'todo', "Set todo.display to '{$_POST['display']}'.", 'user');
  }

?>
<form class="settings-form settings-form--spaced" x-post="/settings/todo/edit" x-on="change" x-target="#todo-settings">
  <label>
    Default query
    <input name="default-query" type="text" placeholder="<?= esc_attr(TODO_DEFAULT_QUERY) ?>"
      value="<?= esc_attr(\config\canonical_value('todo.default-query') ?? '') ?>">
  </label>
</form>

<form class="settings-form settings-form--spaced" x-post="/settings/todo/edit" x-on="change" x-target="#todo-settings">
  <label>
    Layout
    <?php \forms\options('layout', [
      'masonry' => 'Masonry',
      'horizontal' => 'Horizontal',
    ], \config\fresh_value('todo.layout'), flat: true) ?>
  </label>

  <label>
    Display
    <?php \forms\options('display', [
      'tag' => 'By tag',
      'status' => 'By status',
    ], \config\fresh_value('todo.display'), flat: true) ?>
  </label>
</form>

<form class="settings-form settings-form--spaced" x-post="/settings/todo/edit" x-on="change" x-target="#todo-settings">
  <div>
    <label for="recurrence-horizon">Recurrence horizon</label>
    <div class="settings-form__phrase">
      Tasks reappear
      <input id="recurrence-horizon" name="recurrence_horizon" type="number" min="0"
        placeholder="<?= esc_attr(TODO_RECURRENCE_HORIZON) ?>"
        value="<?= esc_attr(\config\canonical_value('todo.recurrence-horizon') ?? '') ?>"> days before their deadline
    </div>
  </div>
</form>
