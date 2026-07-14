<?php

  $query = cast_string(@$_GET['q'] ?? @$_POST['q']) ?? "";

  $addresses = array_filter(\store\list_addresses(), fn($a) =>
    $query == "" || mb_stripos(address_line($a) . " " . $a['label'], $query) !== false);

?>
<?php foreach($addresses as $a): ?>
  <div
    class="listing__item address-item"
    data-id="<?= esc_attr($a['id']) ?>"
    data-label="<?= esc_attr(@$a['label']) ?>"
    data-street-name="<?= esc_attr(@$a['street_name']) ?>"
    data-street-number="<?= esc_attr(@$a['street_number']) ?>"
    data-postal-code="<?= esc_attr(@$a['postal_code']) ?>"
    data-city="<?= esc_attr(@$a['city']) ?>"
    data-province="<?= esc_attr(@$a['province']) ?>"
    data-country="<?= esc_attr(@$a['country']) ?>"
    data-timezone="<?= esc_attr(@$a['timezone']) ?>"
    data-note="<?= esc_attr(@$a['note']) ?>"
  >
    <?php if($a['label']): ?><strong><?= esc_inner($a['label']) ?></strong> — <?php endif ?>
    <?= esc_inner(address_line($a)) ?>
  </div>
<?php endforeach ?>
<?php if(!$addresses): ?><p class="empty">No addresses.</p><?php endif ?>
