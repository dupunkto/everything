<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Wishlist</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/wishlist.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main" z-nav=".listing__item">
      <input
        type="hidden"
        id="wishlist-filter"
        name="show"
        value="dream"
        x-get="/wishlist/listing"
        x-on="input"
        x-target="#wishlist-listing"
      >

      <section id="wishlist-listing" x-get="/wishlist/listing" x-data="#wishlist-filter"></section>
    </main>
  </body>
</html>
