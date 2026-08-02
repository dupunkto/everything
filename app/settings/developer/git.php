<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../../shell/head.php" ?>
    <title>Git export</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/developer"; include __DIR__ . "/../back.php" ?>
        <h2>Git export</h2>
      </header>

      <section id="git-settings" x-get="/settings/developer/git/edit">
        <?php fragment("settings/developer/git/edit") ?>
      </section>
    </main>
  </body>
</html>
