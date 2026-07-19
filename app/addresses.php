<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Addresses</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/addresses.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--semi-wide main--scrollable" z-nav="#address-search input, .address-item">
      <header class="page-header"><h2>Addresses</h2></header>

      <section id="address-editor" x-get="/addresses/edit"><?php fragment("addresses/edit") ?></section>

      <p class="addresses-or">or</p>

      <form id="address-search">
        <input
          name="q"
          type="search"
          z-key="/"
          placeholder="find existing address…"
          x-get="/addresses/listing"
          x-on="input"
          x-target="#addresses-list"
          x-data="#address-search"
        >
      </form>

      <section id="addresses-list" class="listing main__scroll" x-get="/addresses/listing" x-data="#address-search">
        <?php fragment("addresses/listing") ?>
      </section>
    </main>
  </body>
</html>
