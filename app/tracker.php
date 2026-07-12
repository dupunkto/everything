<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Time tracking</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/tracker.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--wide">
      <section id="tracker-new" x-get="/tracker/new"></section>
      <section id="tracker-listing" x-get="/tracker/listing"></section>
    </main>
  </body>
</html>
