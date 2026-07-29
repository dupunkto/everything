<?php

  [$page, $offset] = listing_page();

  [$logs, $has_more] = listing_batch(
    \store\list_logs_paginated(LISTING_PAGE_SIZE + 1, offset: $offset)
  );

  $edit_url = function($log) {
    $id = rawurlencode($log['record_id']);

    return match($log['table_name']) {
      'addresses' => "/addresses?edit=$id",
      'bookmarks' => "/bookmarks/edit?id=$id",
      'contacts' => "/contacts?kind=person&edit=$id",
      'notes' => "/notes/edit?id=$id",
      'organisations' => "/contacts?kind=org&edit=$id",
      'tasks' => "/todo/edit?id=$id",
      'timings' => "/tracker?edit=$id",
      'wishes' => "/wishlist/edit?id=$id",
      default => null,
    };
  };

?>
<?php foreach($logs as $log): ?>
  <?php $entity = $log['table_name'] . "/" . $log['record_id'] ?>
  <?php $url = $edit_url($log) ?>
  <?php $datetime = (new \DateTimeImmutable($log['changed_at'], timezone: new \DateTimeZone("UTC")))->format('c') ?>
  <tr>
    <td><time datetime="<?= esc_attr($datetime) ?>" local><?= esc_inner($log['changed_at']) ?> UTC</time></td>
    <td><?= esc_inner($log['message']) ?></td>
    <td><?= esc_inner($log['operation']) ?></td>
    <td><?= esc_inner($log['author']) ?></td>
    <td>
      <?php if($url): ?>
        <a class="logs__entity" href="<?= esc_attr($url) ?>" title="<?= esc_attr($entity) ?>"><?= esc_inner($entity) ?></a>
      <?php else: ?>
        <span class="logs__entity" title="<?= esc_attr($entity) ?>"><?= esc_inner($entity) ?></span>
      <?php endif ?>
    </td>
  </tr>
<?php endforeach ?>
<?php if($has_more) infinite_scroll("/logs/listing?page=" . ($page + 1), tag: 'tr', colspan: 5) ?>
