<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <h2>Settings</h2>
      </header>

      <ul class="settings-menu">
        <li><a href="<?= CANONICAL ?>/settings/general">General</a></li>
        <?php if(defined('GIT_SHA')): ?><li><a href="<?= CANONICAL ?>/settings/updates">Updates</a></li><?php endif ?>
        <li><a href="<?= CANONICAL ?>/settings/setup">Setup</a></li>
        <li><a href="<?= CANONICAL ?>/settings/applications">Applications</a></li>
        <?php if(DEVELOPER_MODE): ?><li><a href="<?= CANONICAL ?>/settings/developer">Developer</a></li><?php endif ?>
        <li><a href="<?= CANONICAL ?>/about">About</a></li>
      </ul>
    </main>
  </body>
</html>
