<?php

  if(isset($_POST["status"], $_POST["recurrence"], $_POST["urgent"], $_POST["open_date"], $_POST["open_time"], $_POST["due_date"], $_POST["due_time"], $_POST["expire_date"], $_POST["expire_time"])) {
    $all_day = cast_string($_POST['due_date']) != null
      && cast_string(@$_POST['due_time']) == null;

    $id = \store\create_task(
      cast_string($_POST['title']),
      cast_string(@$_POST['content']),
      cast_string($_POST['status']),
      cast_boolean($_POST['urgent']),
      cast_string($_POST['recurrence']),
      cast_datetime_utc($_POST['open_date'], $_POST['open_time']),
      cast_datetime_utc($_POST['due_date'], @$_POST['due_time'] ?: "00:00"),
      $all_day,
      cast_datetime_utc($_POST['expire_date'], @$_POST['expire_time'] ?: "00:00"),
      cast_string($_POST['comment'])
    ) or fail("Could not save task '" . $_POST['title'] . "'.");

    \store\set_task_tags($id, $_POST['tags'] ?? []);
    \store\insert_log('tasks', $id, "Created task.", 'user')
      or fail("Could not create audit entry.");

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
    <script src="<?= CANONICAL ?>/client/circle.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main main--semi-wide">
      <form id="todo-form" class="todo-form" x-post="/todo/new">
        <div class="actions">
          <a class="button" href="/todo" z-key="escape">Cancel</a>
          <button>Save</button>
        </div>

        <div class="title-check" z-circle>
          <input name="title" type="text" placeholder="Title" required autofocus>
          <?php circle() ?>
          <label class="title-check__urgent" title="Circle">
            <input type="hidden" name="urgent" value="false">
            <input type="checkbox" name="urgent" value="true" aria-label="Circle">
            <i class="fa-regular fa-flag"></i>
            <i class="fa-solid fa-flag"></i>
          </label>
        </div>

        <?php tags_field() ?>

        <div class="todo-form__meta">
          <div class="field">
            <label for="status">Status</label>
            <?php \forms\options("status",
              ["backlog", "todo", "wip", "blocked", "done", "nvm"], capitalize: false, selected: "todo") ?>
          </div>

          <div class="field">
            <label for="comment">Comment</label>
            <input type="text" name="comment" placeholder="Why is this task blocked or backlogged?">
          </div>

          <div class="field">
            <label for="recurrence">Repeat</label>
            <input type="text" id="recurrence" name="recurrence" placeholder="cron or number of days">
          </div>

          <div class="field">
            <label for="open_date">Open</label>
            <span class="datetime-pair">
              <input type="date" id="open_date" name="open_date" value="<?= local_date("Y-m-d") ?>" required>
              <input type="time" name="open_time" value="<?= local_date("H:i") ?>" required>
            </span>
          </div>

          <div class="field">
            <label for="due_date">Due</label>
            <span class="datetime-pair">
              <input type="date" id="due_date" name="due_date">
              <input type="time" name="due_time">
            </span>
          </div>

          <div class="field">
            <label for="expire_date">Expire</label>
            <span class="datetime-pair">
              <input type="date" id="expire_date" name="expire_date">
              <input type="time" name="expire_time">
            </span>
          </div>
        </div>
      </form>
    </main>
  </body>
</html>