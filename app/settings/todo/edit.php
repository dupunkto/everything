<?php

  if(isset($_POST['recurrence_horizon'])) {
    \store\update_config('todo.recurrence-horizon', cast_int($_POST['recurrence_horizon']))
      or fail("Could not update recurrence horizon.");
  }

  if(isset($_POST['layout'])) {
    if(!in_array($_POST['layout'], ['masonry', 'horizontal']))
      fail("Invalid 'layout' parameter.", status: 400);

    \store\update_config('todo.layout', $_POST['layout'])
      or fail("Could not update ToDo layout.");
  }

  if($_POST) {
    \store\put_log('config', 'todo', "Updated ToDo settings.", 'user')
      or fail("Could not create audit entry.");
  }

?>
<form class="settings-form settings-form--spaced" x-post="/settings/todo/edit" x-on="change" x-target="#todo-settings">
  <label>
    Recurrence horizon (days)
    <input name="recurrence_horizon" type="number" min="0"
      placeholder="<?= esc_attr(TODO_RECURRENCE_HORIZON) ?>"
      value="<?= esc_attr(\config\canonical_value('todo.recurrence-horizon') ?? '') ?>">
  </label>
</form>

<form class="settings-form" x-post="/settings/todo/edit" x-on="change" x-target="#todo-settings">
  <label>
    Layout
    <?php \forms\options('layout', [
      'masonry' => 'Masonry',
      'horizontal' => 'Horizontal scroll',
    ], \config\fresh_value('todo.layout'), flat: true) ?>
  </label>
</form>
