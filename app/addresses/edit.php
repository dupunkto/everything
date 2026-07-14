<?php
  // The address editor; blank for a new address, filled when ?id is given.

  $a = null;
  if(@$_GET['id']) {
    $a = \store\get_address($_GET['id']) or fail("Address not found.", status: 404);
  }

  $maps = $a ? maps_url(address_line($a)) : null;
?>
<form x-post="/addresses/new" x-target="#addresses-list" x-refresh="#address-editor">
  <input name="addr_id" type="hidden" value="<?= esc_attr(@$a['id']) ?>">
  <div>
    <input name="addr_label" placeholder="label" value="<?= esc_attr(@$a['label']) ?>">
    <input name="addr_street_name" placeholder="street" required value="<?= esc_attr(@$a['street_name']) ?>">
    <input name="addr_street_number" placeholder="number" required value="<?= esc_attr(@$a['street_number']) ?>">
    <input name="addr_postal_code" placeholder="postal code" required value="<?= esc_attr(@$a['postal_code']) ?>">
  </div>
  <div>
    <input name="addr_city" placeholder="city" required value="<?= esc_attr(@$a['city']) ?>">
    <input name="addr_province" placeholder="province" required value="<?= esc_attr(@$a['province']) ?>">
    <input name="addr_country" placeholder="country" required value="<?= esc_attr(@$a['country']) ?>">
    <input name="addr_timezone" placeholder="timezone" required value="<?= esc_attr(@$a['timezone']) ?>">
  </div>
  <div class="actions">
    <div>
      <?php if($a): ?>
        <button type="button" z-key="escape" x-get="/addresses/edit" x-target="#address-editor">Cancel</button>
      <?php endif ?>
      <button><?= $a ? "Save address" : "Add address" ?></button>
    </div>
    <?php if($maps): ?>
      <a class="button" href="<?= esc_attr($maps) ?>">Directions &rarr;</a>
    <?php endif ?>
  </div>
</form>
