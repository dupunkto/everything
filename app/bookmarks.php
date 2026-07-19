<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Bookmarks</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/bookmarks.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--scrollable bookmarks" z-nav=".page-header__search, .listing__item">
      <div class="page-header">
        <h1 class="page-header__title"><strong>Bookmarks</strong></h1>
        <input
          class="page-header__search"
          type="search"
          name="q"
          z-key="/"
          placeholder="keywords +acme"
          x-get="/bookmarks/listing"
          x-on="input"
          x-target="#bookmarks-listing"
        >
      </div>

      <section id="bookmarks-listing" class="main__scroll" x-get="/bookmarks/listing"><?php fragment("bookmarks/listing") ?></section>
    </main>
  </body>
</html>
