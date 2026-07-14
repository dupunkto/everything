<?php $addresses = \store\list_addresses(); ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Addresses</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/addresses.css">
    <script src="<?= CANONICAL ?>/client/addresses.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--semi-wide">
      <header class="page-header"><h2>Addresses</h2></header>

      <form id="address-editor" x-post="/addresses/new" x-target="#addresses-list" x-refresh="#addresses-list">
        <input name="addr_id" type="hidden">
        <div>
          <input name="addr_label" placeholder="label">
          <input name="addr_street_name" placeholder="street" required>
          <input name="addr_street_number" placeholder="number" required>
          <input name="addr_postal_code" placeholder="postal code" required>
        </div>
        <div>
          <input name="addr_city" placeholder="city" required>
          <input name="addr_province" placeholder="province" required>
          <input name="addr_country" placeholder="country" required>
          <input name="addr_timezone" placeholder="timezone" required>
        </div>
        <div class="actions">
          <div>
            <button type="button" data-address-cancel hidden>Cancel</button>
            <button data-address-submit>Add address</button>
          </div>
          <a class="button" data-address-directions hidden>Directions &rarr;</a>
        </div>
      </form>

      <p class="addresses-or">or</p>

      <form id="address-search">
        <input
          name="q"
          type="search"
          placeholder="find existing address…"
          x-get="/addresses/listing"
          x-on="input"
          x-target="#addresses-list"
          x-data="#address-search"
        >
      </form>

      <section id="addresses-list" class="listing" x-get="/addresses/listing" x-data="#address-search"></section>
    </main>
  </body>
</html>
