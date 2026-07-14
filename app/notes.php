<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Notes</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/notes.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--wide notes" z-nav=".page-header__search, .note-card">
      <div class="page-header">
        <h1 class="page-header__title"><strong>Notes</strong></h1>
        <input
          class="page-header__search"
          type="search"
          name="q"
          z-key="/"
          placeholder="keywords +acme"
          x-get="/notes/listing"
          x-on="input"
          x-target="#notes-listing"
        >
      </div>

      <section id="notes-listing" x-get="/notes/listing"></section>
    </main>
  </body>
</html>
