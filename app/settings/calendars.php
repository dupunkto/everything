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
        <h2>Calendars</h2>
        <a class="button" x-post="/settings/calendars/new" x-target="#calendars-listing">Add calendar</a>
      </header>

      <section id="calendars-listing" x-get="/settings/calendars/listing"></section>
    </main>
  </body>
</html>