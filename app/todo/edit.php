<?php

  $task = \store\get_task(@$_GET['id'] ?? @$_POST['id'])
    or fail("Task not found.", status: 404);

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

    if($_POST['status'] !== $task['status'] || @$_POST['comment']) {
      \store\set_task_status($_POST['id'], $_POST['status'], @$_POST['comment'])
        or fail("Could not update task status.");
    }

    $task = \store\get_task($_POST['id'])
      or fail("Task not found.", status: 404);
  }

  $log = \store\get_task_log($task['id']);

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
      <form id="todo-editor" x-post="/todo/edit" x-on="change" x-target="@document">
        <input type="hidden" name="id" value="<?= esc_attr($task['id']) ?>">

        <section>
          <input name="title" type="text" placeholder="Title" value="<?= esc_attr($task['title']) ?>">
          <textarea name="content" placeholder="What to do...?"><?= esc_inner($task['content']) ?></textarea>

          <?php if($log): ?>
            <h2>History</h2>
            <?php $previous = null; foreach($log as $entry): ?>
              <?php $change = $entry['status'] !== $previous ?>
              <?php $previous = $entry['status'] ?>

              <div class="log-entry <?php if($change) echo "status" ?> <?php if($entry['comment']) echo "comment" ?>">
                <?php if($change): ?>
                  Status is <b><?= esc_inner($entry['status']) ?></b><?php if($entry['comment']) echo ", with comment:" ?>
                  <time><?= esc_attr(local_date("Y-m-d H:i", $entry['date'])) ?></time>
                <?php endif ?>
                <?php if($entry['comment']): ?>
                  <div class="message">
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

          <?php if($task['recurrence']): ?>
            <p class="next"><small>Next occurence at ...</small></p>
          <?php endif ?>
        </aside>
      </form>

      <script type="module">
        const form = document.getElementById('todo-editor');
        const select = form?.querySelector('[name="status"]');
        const comment = form?.querySelector('[name="comment"]');

        if(form && select && comment) {
          comment.addEventListener('change', (e) => e.stopPropagation());

          select.addEventListener('focus', () => { select.dataset.prior = select.value; });
          select.addEventListener('change', (e) => {
            const status = select.value;
            if(status !== 'blocked' && status !== 'backlog') return;

            e.stopPropagation();

            const msg = status === 'blocked' ? 'Why was this task blocked?' : 'Why was this task backlogged?';
            const reason = prompt(msg);

            if(reason === null) { select.value = select.dataset.prior; return; }

            comment.value = reason;
            form.dispatchEvent(new Event('change'));
          });
        }
      </script>
    </main>
  </body>
</html>
