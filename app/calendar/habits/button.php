<?php
  $icon_color = $habit['done'] ? $habit['contrast_color'] : $habit['color'];
?>
<form class="day__habit<?= $habit['done'] ? ' day__habit--done' : '' ?>" x-post="/calendar/habits/log" x-target="@self" x-replace="outerHTML"
      style="--habit-color: <?= esc_attr($habit['color']) ?>; --habit-icon-color: <?= esc_attr($icon_color) ?>">
  <input type="hidden" name="id" value="<?= esc_attr($habit['id']) ?>">
  <input type="hidden" name="date" value="<?= esc_attr($habit['date']) ?>">
  <button type="submit" title="<?= esc_attr($habit['title']) ?>">
    <i class="fa-solid <?= esc_attr($habit['icon']) ?>"></i>
  </button>
</form>
