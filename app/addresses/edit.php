<?php
  // The address editor; blank for a new address, filled when ?id is given.

  if(isset($_GET['id'])) {
    $address = \store\get_address($_GET['id'])
      or fail("Address not found.", status: 404);
  }

?>
<form x-post="/addresses/new" x-target="#addresses-list" x-refresh="#address-editor">
  <input name="addr_id" type="hidden" value="<?= esc_attr(@$address['id']) ?>">
  <div>
    <input name="addr_label" type="text" z-key="n" placeholder="label" value="<?= esc_attr(@$address['label']) ?>">
    <input name="addr_street_address" placeholder="street address" required value="<?= esc_attr(@$address['street_address']) ?>">
    <input name="addr_postal_code" placeholder="postal code" value="<?= esc_attr(@$address['postal_code']) ?>">
  </div>
  <div>
    <input name="addr_city" placeholder="city" value="<?= esc_attr(@$address['city']) ?>">
    <input name="addr_province" placeholder="province" value="<?= esc_attr(@$address['province']) ?>">
    <?php \forms\options('addr_country', [...country_options(), '' => "No country"],
      isset($address) ? $address['country'] : COUNTRY, flat: true) ?>
  </div>
  <div class="actions">
    <div>
      <?php if(isset($_GET['id'])): ?>
        <button type="button" formnovalidate z-key="escape" x-get="/addresses/edit" x-target="#address-editor" x-blur>Cancel</button>
      <?php endif ?>
      <button><?= isset($_GET['id']) ? "Save" : "Add address" ?></button>
    </div>
    <div>
      <?php if(isset($_GET['id'])): ?>
        <button type="button" z-key="d" x-delete="/addresses/delete?id=<?= esc_attr($address['id']) ?>" x-target="#addresses-list" x-refresh="#address-editor" x-data="#address-search" z-confirm="Delete this address and remove it from all contacts and organisations?">Delete</button>
      <?php endif ?>
    </div>
  </div>
</form>
