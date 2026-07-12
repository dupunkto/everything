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

      <ul>
        <li><a href="<?= CANONICAL ?>/settings/tags">Tags</a></li>
        <li><a href="<?= CANONICAL ?>/settings/calendars">Calendars</a></li>
        <li><a href="<?= CANONICAL ?>/settings/subscriptions">Subscriptions</a></li>
      </ul>
    </main>
  </body>
</html>
