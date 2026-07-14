<?php

  $query = cast_string(@$_GET['q'] ?? @$_POST['q']) ?? "";

  $addresses = array_filter(\store\list_addresses(), fn($a) =>
    $query == "" || mb_stripos(address_line($a) . " " . $a['label'], $query) !== false);

?>
<?php foreach($addresses as $a): ?>
  <div class="listing__item address-item" x-get="/addresses/edit?id=<?= esc_attr($a['id']) ?>" x-on="click" x-target="#address-editor">
    <?php if($a['label']): ?><strong><?= esc_inner($a['label']) ?></strong> — <?php endif ?>
    <?= esc_inner(address_line($a)) ?>
  </div>
<?php endforeach ?>
<?php if(!$addresses): ?><p class="empty">No addresses.</p><?php endif ?>
