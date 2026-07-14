<?php
  // The address editor; blank for a new address, filled when ?id is given.

  if(isset($_GET['id'])) {
    $address = \store\get_address($_GET['id'])
      or fail("Address not found.", status: 404);
  }

?>
<form x-post="/addresses/new" x-target="#addresses-list" x-refresh="#address-editor" x-data="#address-search">
  <input name="addr_id" type="hidden" value="<?= esc_attr(@$address['id']) ?>">
  <div>
    <input name="addr_label" placeholder="label" value="<?= esc_attr(@$address['label']) ?>">
    <input name="addr_street_name" placeholder="street" required value="<?= esc_attr(@$address['street_name']) ?>">
    <input name="addr_street_number" placeholder="number" required value="<?= esc_attr(@$address['street_number']) ?>">
    <input name="addr_postal_code" placeholder="postal code" required value="<?= esc_attr(@$address['postal_code']) ?>">
  </div>
  <div>
    <input name="addr_city" placeholder="city" required value="<?= esc_attr(@$address['city']) ?>">
    <input name="addr_province" placeholder="province" required value="<?= esc_attr(@$address['province']) ?>">
    <input name="addr_country" placeholder="country" required value="<?= esc_attr(@$address['country']) ?>">
    <input name="addr_timezone" placeholder="timezone" required value="<?= esc_attr(@$address['timezone']) ?>">
  </div>
  <div class="actions">
    <div>
      <?php if(isset($_GET['id'])): ?>
        <button type="button" z-key="escape" x-get="/addresses/edit" x-target="#address-editor">Cancel</button>
      <?php endif ?>
      <button><?= isset($_GET['id']) ? "Save" : "Add address" ?></button>
    </div>
    <div>
      <?php if(isset($_GET['id'])): ?>
        <button type="button" x-delete="/addresses/delete?id=<?= esc_attr($address['id']) ?>" x-target="#addresses-list" x-refresh="#address-editor" x-data="#address-search" x-confirm="Delete this address and remove it from all contacts and organisations?">Delete</button>
      <?php endif ?>
    </div>
  </div>
</form>
