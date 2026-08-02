<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Calendar settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/setup"; include __DIR__ . "/back.php" ?>
        <h2>Calendar</h2>
      </header>

      <ul class="settings-menu">
        <li><a href="<?= CANONICAL ?>/settings/calendars">Calendars</a></li>
        <li><a href="<?= CANONICAL ?>/settings/subscriptions">Subscriptions</a></li>
        <li><a href="<?= CANONICAL ?>/settings/habits">Habits</a></li>
        <li><a href="<?= CANONICAL ?>/settings/shares">Sharing</a></li>
      </ul>
    </main>
  </body>
</html>
