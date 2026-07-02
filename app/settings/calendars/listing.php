<ul>
  <?php foreach(\store\list_calendars() as $calendar): ?>
    <li>
      <form class="calendars-editor" x-post="/settings/calendars/edit" x-on="change" x-target="#calendars-listing">
        <input name="id" type="hidden" value="<?= $calendar['id'] ?>">
        <input name="color" type="color" value="<?= esc_attr($calendar['color']) ?>">
        <input name="title" type="text" value="<?= esc_attr($calendar['title']) ?>" placeholder="Title">
        <input name="subtitle" type="text" value="<?= esc_attr($calendar['subtitle'] ?? '') ?>" placeholder="Subtitle">
        <button type="button" x-delete="/settings/calendars/delete?id=<?= $calendar['id'] ?>" x-target="#calendars-listing" x-confirm="Delete this calendar and all its appointments?">&times;</button>
      </form>
    </li>
  <?php endforeach ?>
</ul>
