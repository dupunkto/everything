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
        <?php $parent = "/settings/tracker"; include __DIR__ . "/back.php" ?>
        <h2>Quotas</h2>
        <section id="quota-new" x-get="/settings/quotas/new"><?php fragment("settings/quotas/new") ?></section>
      </header>

      <section id="quotas-listing" x-get="/settings/quotas/listing"><?php fragment("settings/quotas/listing") ?></section>
    </main>
  </body>
</html>
