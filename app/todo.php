<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>ToDo</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/todo.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--wide todo" z-nav="#todo-search, .listing__item">
      <input
        id="todo-search"
        class="page-header__search"
        name="q"
        placeholder="is:todo +acme"
        value="is:todo"
        x-get="/todo/listing"
        x-on="input"
        x-target="#todo-listing"
      >

      <section id="todo-listing" x-get="/todo/listing" x-data="#todo-search"></section>
    </main>
  </body>
</html>
