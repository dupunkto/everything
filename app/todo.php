<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>ToDo</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/todo.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="wide">
      <header class="bar">
        <input id="todo-search" name="q" placeholder="Search query..." value="is:todo" x-get="/todo/listing" x-on="input" x-target="#todo-listing">
      </header>

      <section id="todo-listing" x-get="/todo/listing" x-data="#todo-search"></section>
    </main>
  </body>
</html>
