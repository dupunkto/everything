<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Quotas</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <h2>Quotas</h2>
        <section id="quota-new" x-get="/settings/tracker/new"><?php fragment("settings/tracker/new") ?></section>
      </header>

      <section id="quotas-listing" x-get="/settings/tracker/listing"><?php fragment("settings/tracker/listing") ?></section>
    </main>
  </body>
</html>
