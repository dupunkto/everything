<?php

  if(isset($_POST["status"], $_POST["recurrence"], $_POST["urgent"], $_POST["open_date"], $_POST["due_date"], $_POST["expiration_date"])) {
    \store\create_task(
      $_POST['title'],
      $_POST['content'],
      $_POST['status'],
      $_POST['urgent'],
      $_POST['recurrence'],
      $_POST['open_date'],
      $_POST['due_date'],
      $_POST['expiration_date'],
      $_POST['comment']
    ) or fail("Could not save task '" . $_POST['title'] . "'.");

    http_response_code(303);
    header("Location: /todo"); exit;
  }

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>ToDo</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/todo.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="semi-wide">
      <form id="todo-form" x-post="/todo/new">
        <div class="meta">
          <div class="field">
            <label for="status">Status</label>
            <?php \forms\options("status",
              ["backlog", "todo", "blocked", "done", "nvm"], capitalize: false, selected: "todo") ?>
          </div>

          <div class="field">
            <label for="recurrence">Repeat</label>
            <input type="text" id="recurrence" name="recurrence" placeholder="cron or number of days">
          </div>

          <div class="field">
            <label for="urgent">Circled</label>
            <div>
              <input type="hidden" name="urgent" value="false">
              <input type="checkbox" id="urgent" name="urgent" value="true">
            </div>
          </div>

          <div class="field">
            <label for="open_date">Open</label>
            <input type="datetime-local" id="open_date" name="open_date" value="<?= local_date("Y-m-d H:i") ?>">
          </div>

          <div class="field">
            <label for="due_date">Due</label>
            <input type="datetime-local" id="due_date" name="due_date">
          </div>

          <div class="field">
            <label for="expiration_date">Expire</label>
            <input type="datetime-local" id="expiration_date" name="expiration_date">
          </div>
        </div>

        <input name="title" type="text" placeholder="Title">
        <textarea name="content" placeholder="What to do...?"></textarea>

        <div id="todo-form-comment" class="field">
          <label for="comment">Comment</label>
          <input type="text" name="comment" placeholder="Why is this task blocked or backlogged?">
        </div>

        <button>Save</button>
      </form>
    </main>
  </body>
</html>