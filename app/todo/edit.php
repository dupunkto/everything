<?php

  if(isset($_POST['id'])) {
    \store\update_task(
      $_POST['id'],
      $_POST['title'],
      $_POST['content'],
      $_POST['urgent'],
      $_POST['recurrence'],
      $_POST['open_date'],
      $_POST['due_date'],
      $_POST['expiration_date']
    ) or fail("Could not update task.");

    \store\set_task_status($_POST['id'], $_POST['status'], @$_POST['comment'])
      or fail("Could not update task status.");

    if(isset($_POST['close'])) {
      http_response_code(303);
      header("Location: /todo"); exit;
    }
  }

  $task = \store\get_task(@$_GET['id'] ?? @$_POST['id'])
    or fail("Task not found.", status: 404);

  $log = \store\get_task_log($task['id']);

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>ToDo</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/todo.css">
    <script src="<?= CANONICAL ?>/client/circle.js" type="module"></script>
    <script src="<?= CANONICAL ?>/client/todo.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main main--semi-wide">
      <form id="todo-editor" class="todo-editor" x-post="/todo/edit" x-on="change" x-target="@document">
        <input type="hidden" name="id" value="<?= esc_attr($task['id']) ?>">

        <?php $status_for = fn($target) => $task['status'] == $target ? "todo" : $target ?>
        <button type="button" z-key="b" z-set=".todo-editor [name=status]" value="<?= $status_for('backlog') ?>" hidden></button>
        <button type="button" z-key="c" z-set=".todo-editor [name=status]" value="<?= $status_for('done') ?>" hidden></button>
        <button type="button" z-key="u" z-set=".todo-editor [name=status]" value="todo" hidden></button>
        <button type="button" z-key="s" z-set=".todo-editor [name=status]" value="<?= $status_for('nvm') ?>" hidden></button>
        <button type="submit" name="close" value="1" z-key="escape mod+enter" hidden></button>

        <section>
          <span class="circled-field" z-circle>
            <?php if(cast_boolean($task['urgent'])) circle() ?>
            <input name="title" type="text" placeholder="Title" value="<?= esc_attr($task['title']) ?>">
          </span>
          <textarea name="content" placeholder="What to do...?"><?= esc_inner($task['content']) ?></textarea>

          <?php if($log): ?>
            <h2>History</h2>
            <?php $previous = null; foreach($log as $entry): ?>
              <?php $change = $entry['status'] !== $previous ?>
              <?php $previous = $entry['status'] ?>

              <div class="log-entry <?php if($change) echo "log-entry--status" ?> <?php if($entry['comment']) echo "log-entry--comment" ?>">
                <?php if($change): ?>
                  Status is <b><?= esc_inner($entry['status']) ?></b><?php if($entry['comment']) echo ", with comment:" ?>
                  <time><?= esc_attr(local_date("Y-m-d H:i", $entry['date'])) ?></time>
                <?php endif ?>
                <?php if($entry['comment']): ?>
                  <div class="log-entry__message">
                    <?php if(!$change): ?>
                      <time><?= esc_attr(local_date("Y-m-d H:i", $entry['date'])) ?></time>
                    <?php endif; ?>
                    <p><?= esc_inner($entry['comment']) ?></p>
                  </div>
                <?php endif ?>
                
              </div>
            <?php endforeach ?>
          <?php endif ?>

          <textarea rows="3" name="comment" placeholder="Add a comment..."></textarea>
          <div class="actions">
            <a class="button" href="/todo/delete?id=<?= esc_attr($task['id']) ?>" z-confirm="Delete this task?">Delete</a>
            <button x-post="/todo/edit" x-data="#todo-editor" x-target="@document">Comment</button>
          </div>
        </section>

        <aside>
          <?php \forms\options("status",
              ["backlog", "todo", "blocked", "done", "nvm"],
              selected: $task['status'],
              capitalize: false) ?>

          <div class="field">
            <label for="open_date">Open</label>
            <input type="datetime-local" id="open_date" name="open_date" value="<?= esc_attr(local_date("Y-m-d H:i", $task['open_date'])) ?>">
          </div>

          <div class="field">
            <label for="due_date">Due</label>
            <input type="datetime-local" id="due_date" name="due_date" value="<?= esc_attr($task['due_date'] ? local_date("Y-m-d H:i", $task['due_date']) : '') ?>">
          </div>

          <div class="field">
            <label for="expiration_date">Expire</label>
            <input type="datetime-local" id="expiration_date" name="expiration_date" value="<?= esc_attr($task['expiration_date'] ? local_date("Y-m-d H:i", $task['expiration_date']) : '') ?>">
          </div>

          <div class="field">
            <label for="urgent">Circle</label>
            <div>
              <input type="hidden" name="urgent" value="false">
              <input type="checkbox" id="urgent" name="urgent" value="true" <?php if(filter_var($task['urgent'], FILTER_VALIDATE_BOOLEAN)) echo "checked" ?>>
            </div>
          </div>

          <div class="field">
            <label for="recurrence">Repeat</label>
            <input type="text" id="recurrence" name="recurrence" placeholder="cron or number of days" value="<?= esc_attr($task['recurrence'] ?? '') ?>">
          </div>

          <?php if($task['recurrence'] && $task['next']): ?>
            <p class="todo-editor__next"><small>Next occurrence <?= esc_inner((new DateTime($task['next']))->format("l j M, H:i")) ?></small></p>
          <?php endif ?>
        </aside>
      </form>
    </main>
  </body>
</html>
