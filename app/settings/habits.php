<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/setup/calendar"; include __DIR__ . "/back.php" ?>
        <h2>Habits</h2>
        <a class="button" x-post="/settings/habits/new" x-target="#habits-listing">Add habit</a>
      </header>

      <section id="habits-listing" x-get="/settings/habits/listing"><?php fragment("settings/habits/listing") ?></section>
    </main>
  </body>
</html>
