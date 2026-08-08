<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Shared feeds</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/setup/calendar"; include __DIR__ . "/back.php" ?>
        <h2>Shared feeds</h2>
        <button x-post="/settings/shares/new" x-target="#shares-listing">Add share</button>
      </header>

      <section id="shares-listing" x-get="/settings/shares/listing"><?php fragment("settings/shares/listing") ?></section>
    </main>
  </body>
</html>
