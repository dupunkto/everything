<?php

  if(is_nonempty_str(@$_POST['recurrence_horizon'])) {
    if(!is_numeric($_POST['recurrence_horizon']))
      fail("Invalid 'recurrence_horizon' parameter.", status: 400);

    \store\update_config('todo.recurrence-horizon', $_POST['recurrence_horizon'])
      or fail("Could not update recurrence horizon.");
  }
?>
<form class="settings-form" x-post="/settings/todo/edit" x-on="change" x-target="#todo-settings">
  <label>
    Recurrence horizon (days)
    <input name="recurrence_horizon" type="number" min="0"
      placeholder="<?= esc_attr(TODO_RECURRENCE_HORIZON) ?>"
      value="<?= esc_attr(\config\canonical_value('todo.recurrence-horizon') ?? '') ?>">
  </label>
</form>
