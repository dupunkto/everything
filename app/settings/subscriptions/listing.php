<ul class="settings-listing">
  <?php foreach(\store\list_subscriptions() as $sub): ?>
    <li>
      <form class="settings-editor" x-post="/settings/subscriptions/edit" x-on="change" x-target="#subscriptions-listing">
        <input name="id" type="hidden" value="<?= $sub['id'] ?>">
        <input name="color" type="color" required value="<?= esc_attr($sub['color']) ?>">
        <input name="title" type="text" required value="<?= esc_attr($sub['title']) ?>" placeholder="Title">
        <input name="subtitle" type="text" value="<?= esc_attr($sub['subtitle'] ?? '') ?>" placeholder="Subtitle">
        <input name="url" type="url" required value="<?= esc_attr($sub['url']) ?>" placeholder="iCal URL">
        <input name="filter" type="text" value="<?= esc_attr($sub['filter'] ?? '') ?>" placeholder="Filter">
        <button type="button" x-delete="/settings/subscriptions/delete?id=<?= $sub['id'] ?>" x-target="#subscriptions-listing" x-confirm="Delete this subscription and all synced appointments?">&times;</button>
      </form>
    </li>
  <?php endforeach ?>
</ul>