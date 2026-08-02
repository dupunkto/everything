<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Developer settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings"; include __DIR__ . "/back.php" ?>
        <h2>Developer</h2>
      </header>

      <ul class="settings-menu">
        <li><a href="<?= CANONICAL ?>/logs">Logs</a></li>
        <li><a href="<?= CANONICAL ?>/settings/developer/git">Git</a></li>
        <li><a href="<?= CANONICAL ?>/settings/developer/custom-code">Custom code</a></li>
      </ul>
    </main>
  </body>
</html>
