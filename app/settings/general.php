<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>General settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings"; include __DIR__ . "/back.php" ?>
        <h2>General</h2>
      </header>

      <ul class="settings-menu">
        <?php if(defined('GIT_SHA')): ?><li><a href="<?= CANONICAL ?>/settings/updates">Updates</a></li><?php endif ?>
        <li><a href="<?= CANONICAL ?>/settings/ui">Interface</a></li>
        <li><a href="<?= CANONICAL ?>/settings/locales">Localisation</a></li>
        <li><a href="<?= CANONICAL ?>/shortcuts">Shortcuts</a></li>
      </ul>
    </main>
  </body>
</html>
