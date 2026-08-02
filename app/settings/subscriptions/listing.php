<?php $current_filter = @$_GET['filter'] ?>

<ul class="settings-listing settings-listing--sortable" data-reorder-url="/settings/subscriptions/reorder" data-reorder-target="#subscriptions-listing">
  <?php foreach(\store\list_subscriptions() as $subscription): ?>
    <?php
      $filter = $subscription['filter'] ?? '';
      $has_filter = is_nonempty_str($filter);
      $is_open = $current_filter == $subscription['id'];
      $retain = cast_bool($subscription['history']);
    ?>
    <li class="settings-listing__item" data-order-id="<?= esc_attr($subscription['id']) ?>">
      <form class="settings-editor settings-editor--subscription" x-post="/settings/subscriptions/edit<?php if($current_filter) echo "?filter=" . urlencode($current_filter) ?>" x-on="change" x-target="#subscriptions-listing">
        <div class="settings-editor__row">
          <span class="settings-editor__drag-handle" title="Drag to reorder" data-drag-handle><i class="fa-solid fa-grip"></i></span>
          <input name="id" type="hidden" value="<?= esc_attr($subscription['id']) ?>">
          <input name="color" type="color" required value="<?= esc_attr($subscription['color']) ?>">
          <input name="title" type="text" required value="<?= esc_attr($subscription['title']) ?>" placeholder="Title">
          <input name="subtitle" type="text" value="<?= esc_attr($subscription['subtitle'] ?? '') ?>" placeholder="Subtitle">
          <input name="url" type="url" required value="<?= esc_attr($subscription['url']) ?>" placeholder="iCal URL">
          <button class="settings-editor__icon-button settings-editor__icon-button--square <?php if($has_filter) echo 'settings-editor__icon-button--active' ?>" type="button" title="Show filter" x-get="/settings/subscriptions/listing<?php if(!$is_open) echo "?filter=" . urlencode($subscription['id']) ?>" x-target="#subscriptions-listing"><i class="fa-<?= $is_open ? 'solid' : 'regular' ?> fa-filter"></i></button>
          <button class="settings-editor__icon-button settings-editor__icon-button--square" type="button" title="<?= esc_attr($retain ? 'Do not retain history' : 'Retain history') ?>" x-post="/settings/subscriptions/history?id=<?= esc_attr($subscription['id']) ?><?php if($current_filter) echo "&amp;filter=" . urlencode($current_filter) ?>" x-target="#subscriptions-listing"><i class="fa-<?= $retain ? 'solid' : 'regular' ?> fa-clock"></i></button>
          <button type="button" x-delete="/settings/subscriptions/delete?id=<?= esc_attr($subscription['id']) ?>" x-target="#subscriptions-listing" z-confirm="Delete this subscription and all synced appointments?">&times;</button>
        </div>
        <label id="subscription-<?= $subscription['id'] ?>-filter" class="settings-editor__filter" <?php if(!$is_open) echo "hidden" ?>>
          Filter:
          <input name="filter" type="text" value="<?= esc_attr($filter) ?>">
        </label>
      </form>
    </li>
  <?php endforeach ?>
</ul>