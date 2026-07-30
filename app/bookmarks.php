<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Bookmarks</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/bookmarks.css">
    <script src="<?= CANONICAL ?>/client/bookmarks.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <?php $query = cast_string(@$_GET['q']) ?? "" ?>
    <main class="main main--scrollable bookmarks" z-nav="#bookmarks-search, .listing__item">
      <div class="page-header">
        <h1 class="page-header__title"><strong>Bookmarks</strong></h1>
        <input
          id="bookmarks-search"
          class="page-header__search"
          type="search"
          name="q"
          z-key="/"
          placeholder="keywords +acme"
          value="<?= esc_attr($query) ?>"
          x-get="/bookmarks/listing"
          x-on="input"
          x-target="#bookmarks-listing"
        >
      </div>

      <section id="bookmarks-listing" class="main__scroll" x-get="/bookmarks/listing" x-data="#bookmarks-search"><?php fragment("bookmarks/listing", ["q" => $query]) ?></section>
    </main>
  </body>
</html>
