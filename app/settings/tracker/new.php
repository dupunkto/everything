<?php

  $quotas = \store\list_quotas();
  $used = array_column($quotas, 'tag_id');
  $tags = array_filter(\store\list_tags(), fn($tag) => !in_array($tag['id'], $used));

  if(isset($_POST['tag_id'])) {
    \store\put_quota(
      cast_int($_POST['tag_id']),
      'week',
      1,
      0,
      local_date("Y-m-d")
    ) or fail("Could not create tracker quota.", status: 400);
    \store\put_log('quotas', $_POST['tag_id'], "Created tracker quota.", 'user')
      or fail("Could not create audit entry.");

    include __DIR__ . "/listing.php"; exit;
  }

?>
<?php if($tags): ?>
  <form class="settings-editor settings-editor--quota-new" x-post="/settings/tracker/new" x-target="#quotas-listing" x-refresh="#quota-new">
    <select name="tag_id" required>
      <?php foreach($tags as $tag): ?>
        <option value="<?= esc_attr($tag['id']) ?>"><?= esc_inner($tag['label']) ?></option>
      <?php endforeach ?>
    </select>
    <button type="submit">+</button>
  </form>
<?php endif ?>
