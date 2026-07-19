<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Time tracking</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/tracker.css">
    <script src="<?= CANONICAL ?>/client/tracker.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--wide main--scrollable">
      <section id="tracker-new" x-get="/tracker/new"></section>
      <section id="tracker-listing" class="main__scroll" x-get="/tracker/listing"></section>
      <aside class="tracker-popup-editor" hidden></aside>
    </main>
  </body>
</html>
