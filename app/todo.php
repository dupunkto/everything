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
        <input name="q" x-get="/todo/listing" x-on="input" x-target="#todo-listing" placeholder="Search query...">
      </header>

      <section id="todo-listing" x-get="/todo/listing"></section>
    </main>
  </body>
</html>
