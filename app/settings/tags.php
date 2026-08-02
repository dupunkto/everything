<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
    <script src="<?= CANONICAL ?>/client/draggable.js" defer></script>
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/setup"; include __DIR__ . "/back.php" ?>
        <h2>Tags</h2>
        <a class="button" x-post="/settings/tags/new" x-target="#tags-listing" x-focus="#tags-listing input[name=label]">Add tag</a>
      </header>

      <section id="tags-listing" x-get="/settings/tags/listing"><?php fragment("settings/tags/listing") ?></section>
    </main>
  </body>
</html>
