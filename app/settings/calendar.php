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
        <?php $parent = "/settings/applications"; include __DIR__ . "/back.php" ?>
        <h2>Calendar</h2>
      </header>

      <section id="calendar-settings" x-get="/settings/calendar/edit"><?php fragment("settings/calendar/edit") ?></section>
    </main>
  </body>
</html>
