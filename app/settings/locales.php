<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Localisation</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/general"; include __DIR__ . "/back.php" ?>
        <h2>Localisation</h2>
      </header>

      <section id="locale-settings" x-get="/settings/locales/edit"><?php fragment("settings/locales/edit") ?></section>
    </main>
  </body>
</html>
