<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../../shell/head.php" ?>
    <title>Custom code</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/developer"; include __DIR__ . "/../back.php" ?>
        <h2>Custom code</h2>
      </header>

      <section id="custom-code" x-get="/settings/developer/custom-code/edit">
        <?php fragment("settings/developer/custom-code/edit") ?>
      </section>
    </main>
  </body>
</html>
