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
        <h2>ToDo</h2>
      </header>

      <section id="todo-settings" x-get="/settings/todo/edit"><?php fragment("settings/todo/edit") ?></section>
    </main>
  </body>
</html>
