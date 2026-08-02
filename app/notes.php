<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Notes</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/notes.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <?php $query = cast_str(@$_GET['q']) ?? "" ?>
    <main class="main<?= NOTES_LAYOUT == 'masonry' ? ' main--wide' : '' ?> main--scrollable notes notes--<?= esc_attr(NOTES_LAYOUT) ?>" z-nav="#notes-search, .note-card, .listing__item">
      <div class="page-header">
        <h1 class="page-header__title"><strong>Notes</strong></h1>
        <input
          id="notes-search"
          class="page-header__search"
          type="search"
          name="q"
          z-key="/"
          placeholder="keywords +acme"
          value="<?= esc_attr($query) ?>"
          x-get="/notes/listing"
          x-on="input"
          x-target="#notes-listing"
        >
      </div>

      <button type="button" z-key="r" x-refresh="#notes-listing" hidden></button>

      <section id="notes-listing" class="main__scroll" x-get="/notes/listing" x-data="#notes-search"><?php fragment("notes/listing", ["q" => $query]) ?></section>
    </main>
  </body>
</html>
