<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Wishlist</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/wishlist.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <?php $show = "dream" ?>
    <main class="main main--scrollable" z-nav=".listing__item">
      <input
        type="hidden"
        id="wishlist-filter"
        name="show"
        value="<?= esc_attr($show) ?>"
        x-get="/wishlist/listing"
        x-on="input"
        x-target="#wishlist-listing"
      >

      <section id="wishlist-listing" class="main__scroll" x-get="/wishlist/listing" x-data="#wishlist-filter">
        <?php fragment("wishlist/listing", ["show" => $show]) ?>
      </section>
    </main>
  </body>
</html>
