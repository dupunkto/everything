<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Tracker settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/applications"; include __DIR__ . "/back.php" ?>
        <h2>Tracker</h2>
      </header>

      <ul class="settings-menu">
        <li><a href="<?= CANONICAL ?>/settings/quotas">Quotas</a></li>
      </ul>
    </main>
  </body>
</html>
