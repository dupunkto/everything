<?php
  // We cannot use the defined constant here, because the default
  // might have just changed. Pull a fresh value from the database instead.
  $default_calendar = \config\fresh_value('calendar.default_calendar')
?>

<ul class="settings-listing settings-listing--sortable" data-reorder-url="/settings/calendars/reorder" data-reorder-target="#calendars-listing">
  <?php foreach(\store\list_calendars() as $calendar): ?>
    <?php $is_default = $calendar['id'] == $default_calendar ?>
    <li class="settings-listing__item" data-order-id="<?= esc_attr($calendar['id']) ?>">
      <form class="settings-editor" x-post="/settings/calendars/edit" x-on="change" x-target="#calendars-listing">
        <span class="settings-editor__drag-handle" title="Drag to reorder" data-drag-handle><i class="fa-solid fa-grip"></i></span>
        <input name="id" type="hidden" value="<?= esc_attr($calendar['id']) ?>">
        <input name="color" type="color" required value="<?= esc_attr($calendar['color']) ?>">
        <input name="title" type="text" required value="<?= esc_attr($calendar['title']) ?>" placeholder="Title">
        <input name="subtitle" type="text" value="<?= esc_attr($calendar['subtitle'] ?? '') ?>" placeholder="Subtitle">
        <button class="settings-editor__icon-button settings-editor__icon-button--square" type="button" title="Make default calendar" x-post="/settings/calendars/default?id=<?= esc_attr($calendar['id']) ?>" x-target="#calendars-listing"><i class="fa-<?= $is_default ? 'solid' : 'regular' ?> fa-star"></i></button>
        <button type="button" x-delete="/settings/calendars/delete?id=<?= esc_attr($calendar['id']) ?>" x-target="#calendars-listing" x-confirm="Delete this calendar and all its appointments?">&times;</button>
      </form>
    </li>
  <?php endforeach ?>
</ul>
