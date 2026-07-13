<?php $addresses = \store\list_addresses(); ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Addresses</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/contacts.css">
    <script src="<?= CANONICAL ?>/client/contacts.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--semi-wide">
      <header class="page-header"><h2>Addresses</h2></header>

      <form class="detail__edit" x-post="/addresses/new" x-target="#addresses-list" x-refresh="#addresses-list">
        <input class="repeat__wide" name="addr_pick" list="addresses-list" placeholder="find existing address…" data-address-pick>
        <div class="detail__names">
          <input name="addr_label" placeholder="label">
          <input name="addr_street_name" placeholder="street">
          <input name="addr_street_number" placeholder="nr">
          <input name="addr_postal_code" placeholder="postcode">
        </div>
        <div class="detail__names">
          <input name="addr_city" placeholder="city">
          <input name="addr_province" placeholder="province">
          <input name="addr_country" placeholder="country">
          <input name="addr_timezone" placeholder="timezone">
        </div>
        <div class="actions"><button>Add address</button></div>

        <datalist id="addresses-list">
          <?php foreach($addresses as $a): ?>
            <option value="<?= esc_attr(address_line($a)) ?>"></option>
          <?php endforeach ?>
        </datalist>
        <script type="application/json" id="addresses-data"><?= json_encode(array_combine(array_map('address_line', $addresses), $addresses)) ?: '{}' ?></script>
      </form>

      <section id="addresses-list" class="contacts__list" x-get="/addresses/listing"></section>

      <script type="module">
        // Clicking an existing address fills the form via the shared autofill.
        document.getElementById('addresses-list').addEventListener('click', (e) => {
          const item = e.target.closest('.address-item');
          if(!item) return;
          const pick = document.querySelector('[name="addr_pick"]');
          pick.value = item.dataset.line;
          pick.dispatchEvent(new Event('input', { bubbles: true }));
        });
      </script>
    </main>
  </body>
</html>
