<?php

  $query = cast_string($_GET['q'] ?? $_POST['q'] ?? "");

  $addresses = array_filter(\store\list_addresses(), fn($a) =>
    $query == "" || mb_stripos(address_line($a) . " " . $a['label'], $query) !== false);

?>
<?php foreach($addresses as $a): ?>
  <?php $map_url = maps_url(address_line($a)) ?>
  <div class="address-row <?= $map_url ? 'address-row--mapped' : '' ?>">
    <div class="listing__item address-item" tabindex="0" x-get="/addresses/edit?id=<?= esc_attr($a['id']) ?>" x-on="click" x-target="#address-editor" x-focus="#address-editor [name=addr_label]" z-key="enter e o">
      <?php if($a['label']): ?><strong><?= esc_inner($a['label']) ?></strong> — <?php endif ?>
      <?= esc_inner(address_line($a)) ?>
      <button
        type="button"
        z-key="d"
        z-stop
        x-delete="/addresses/delete?id=<?= esc_attr($a['id']) ?>"
        x-target="#addresses-list"
        x-refresh="#address-editor"
        x-data="#address-search"
        x-confirm="Delete this address and remove it from all contacts and organisations?"
        hidden
      ></button>
      <?php if($map_url): ?>
        <a href="<?= esc_attr($map_url) ?>" z-key="g" z-stop hidden></a>
      <?php endif ?>
    </div>
    <?php if($map_url): ?>
      <a class="button" href="<?= esc_attr($map_url) ?>" aria-label="Directions" title="Directions"><i class="fa-solid fa-map-location-dot"></i></a>
    <?php endif ?>
  </div>
<?php endforeach ?>
<?php if(!$addresses): ?><p class="empty">No addresses.</p><?php endif ?>
