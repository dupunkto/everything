<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Applications</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings"; include __DIR__ . "/back.php" ?>
        <h2>Applications</h2>
      </header>

      <ul class="settings-menu">
        <li><a href="<?= CANONICAL ?>/settings/calendar">Calendar</a></li>
        <li><a href="<?= CANONICAL ?>/settings/todo">ToDo</a></li>
        <li><a href="<?= CANONICAL ?>/settings/notes">Notes</a></li>
        <li><a href="<?= CANONICAL ?>/settings/contacts">Contacts</a></li>
      </ul>
    </main>
  </body>
</html>
