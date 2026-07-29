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
        <?php $parent = "/settings/applications"; include __DIR__ . "/back.php" ?>
        <h2>Notes</h2>
      </header>

      <section id="notes-settings" x-get="/settings/notes/edit"><?php fragment("settings/notes/edit") ?></section>
    </main>
  </body>
</html>
