<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Setup</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings"; include __DIR__ . "/back.php" ?>
        <h2>Setup</h2>
      </header>

      <ul class="settings-menu">
        <li><a href="<?= CANONICAL ?>/settings/setup/mail">Mail & notes</a></li>
        <li><a href="<?= CANONICAL ?>/settings/setup/calendar">Calendar</a></li>
        <li><a href="<?= CANONICAL ?>/settings/tags">Tags</a></li>
        <li><a href="<?= CANONICAL ?>/settings/quotas">Quotas</a></li>
        <li><a href="<?= CANONICAL ?>/settings/connectors">Connectors</a></li>
      </ul>
    </main>
  </body>
</html>
