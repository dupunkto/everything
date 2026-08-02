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
      <section id="tracker-new" x-get="/tracker/new"><?php fragment("tracker/new") ?></section>
      <section id="tracker-listing" class="main__scroll" x-get="/tracker/listing?<?= http_build_query(["id" => @$_GET['edit']]) ?>"><?php fragment("tracker/listing", ["id" => @$_GET['edit']]) ?></section>
      <aside class="popover tracker-popup-editor" hidden>
        <?php if(@$_GET['edit']) fragment("tracker/edit", ["id" => $_GET['edit']]) ?>
      </aside>
    </main>
  </body>
</html>
