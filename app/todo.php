<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>ToDo</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/todo.css">
    <script src="<?= CANONICAL ?>/client/todo.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <?php $query = cast_str(@$_GET['q']) ?? TODO_DEFAULT_QUERY ?>
    <main class="main main--wide main--scrollable todo" z-nav="#todo-search, .listing__item">
      <input
        id="todo-search"
        class="page-header__search"
        type="search"
        name="q"
        z-key="/"
        placeholder="<?= esc_attr(TODO_DEFAULT_QUERY) ?> +acme"
        value="<?= esc_attr($query) ?>"
        x-get="/todo/listing"
        x-on="input"
        x-target="#todo-listing"
      >

      <button type="button" z-key="r" x-refresh="#todo-listing" hidden></button>

      <section id="todo-listing" x-get="/todo/listing" x-data="#todo-search">
        <?php fragment("todo/listing", ["q" => $query]) ?>
      </section>
    </main>
  </body>
</html>
