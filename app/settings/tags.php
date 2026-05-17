<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings/tags.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main>
      <header class="bar">
        <h2>Tags</h2>
        <a class="button" x-post="/settings/tags/new" x-target="#tags-listing">Add tag</a>
      </header>

      <section id="tags-listing" x-get="/settings/tags/listing"></section>
    </main>
  </body>
</html>
