<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Connectors</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/applications"; include __DIR__ . "/back.php" ?>
        <h2>Connectors</h2>
        <button x-post="/settings/connectors/new" x-target="#connectors-listing">Add connector</button>
      </header>

      <section id="connectors-listing" x-get="/settings/connectors/listing"><?php fragment("settings/connectors/listing") ?></section>
    </main>
  </body>
</html>
