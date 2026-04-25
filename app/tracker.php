<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Scattertracker</title>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main>
      <section id="tracker-new" x-get="/tracker/new"></section>
      <section id="tracker-listing" x-get="/tracker/listing"></section>
    </main>
  </body>
</html>
