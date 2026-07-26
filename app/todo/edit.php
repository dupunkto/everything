<?php

  if(isset($_POST['id'])) {
    $all_day = cast_string($_POST['due_date']) != null
      && cast_string(@$_POST['due_time']) == null;

    $open_at = cast_datetime_utc($_POST['open_date'], $_POST['open_time']);
    $due_at = cast_datetime_utc($_POST['due_date'], @$_POST['due_time'] ?: "00:00");

    $recurrence = cast_string($_POST['recurrence']);
    $recurrence_start = new \DateTimeImmutable($due_at ?: $open_at);
    if($recurrence && !\recurrence\valid($recurrence,
      $recurrence_start->setTimezone(new \DateTimeZone(TIMEZONE))))
      fail("Invalid recurrence rule.", status: 400);

    $task = \store\get_task($_POST['id']);
    $fields = \core\diff([...$task, 'tags' => \store\list_task_tag_ids($_POST['id'])],
      title: $_POST['title'], content: $_POST['content'], urgent: $_POST['urgent'],
      recurrence: $recurrence, open_at: $open_at, due_at: $due_at,
      due_all_day: $all_day,
      expire_at: cast_datetime_utc($_POST['expire_date'], @$_POST['expire_time'] ?: "00:00"),
      status: $_POST['status'],
      comment: $_POST['comment'], tags: $_POST['tags'] ?? []);

    \store\transaction(function() use ($recurrence, $open_at, $due_at, $all_day, $fields) {
      \store\update_task(
        $_POST['id'],
        $_POST['title'],
        $_POST['content'],
        $_POST['urgent'],
        $recurrence,
        $open_at,
        $due_at,
        $all_day,
        cast_datetime_utc($_POST['expire_date'], @$_POST['expire_time'] ?: "00:00")
      ) or fail("Could not update task.");

      if(isset($_POST['amend'])) {
        \store\amend_task_status($_POST['id'], $_POST['comment'])
          or fail("Could not amend task status.");
      }
      else {
        \store\set_task_status($_POST['id'], $_POST['status'], $_POST['comment'])
          or fail("Could not update task status.");
      }

      \store\set_task_tags($_POST['id'], $_POST['tags'] ?? []);

      \store\put_audit_log('tasks', $_POST['id'],
        "Updated [" . join(", ", $fields) . "] for tasks/{$_POST['id']}.", 'user')
        or fail("Could not create audit entry.");

      \caldav\mark_resource_changed('task', $_POST['id']);
    });

    if(isset($_POST['close'])) {
      http_response_code(303);
      header("Location: /todo"); exit;
    }
  }

  $task = \store\get_task(@$_GET['id'] ?: @$_POST['id'])
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
        <button type="button" z-key="w" z-set=".todo-editor [name=status]" value="<?= $status_for('wip') ?>" hidden></button>
        <button type="button" z-key="x" z-set=".todo-editor [name=status]" value="<?= $status_for('blocked') ?>" hidden></button>
        <button type="button" z-key="c" z-set=".todo-editor [name=status]" value="<?= $status_for('done') ?>" hidden></button>
        <button type="button" z-key="u" z-set=".todo-editor [name=status]" value="todo" hidden></button>
        <button type="button" z-key="s" z-set=".todo-editor [name=status]" value="<?= $status_for('nvm') ?>" hidden></button>
        <button type="submit" name="close" value="1" z-key="escape mod+enter" hidden></button>

        <section>
          <div class="title-check" z-circle>
            <input name="title" type="text" placeholder="Title" required value="<?= esc_attr($task['title']) ?>">
            <?php circle() ?>
            <label class="title-check__urgent" title="Circle" z-key="m">
              <input type="hidden" name="urgent" value="false">
              <input type="checkbox" name="urgent" value="true" aria-label="Circle" <?php if(filter_var($task['urgent'], FILTER_VALIDATE_BOOLEAN)) echo "checked" ?>>
              <i class="fa-regular fa-flag"></i>
              <i class="fa-solid fa-flag"></i>
            </label>
          </div>
          <textarea name="content" placeholder="What to do...?"><?= esc_inner($task['content']) ?></textarea>

          <?php tags_field(\store\list_task_tags($task['id'])) ?>

          <?php if($log): ?>
            <h2>History</h2>
            <?php
              $last = array_key_last($log);
              $undo = count($log) > 1 && $log[$last]['status'] != $log[$last - 1]['status'];
              $previous = null;
              foreach($log as $key => $entry):
            ?>
              <?php $change = $entry['status'] !== $previous ?>
              <?php $previous = $entry['status'] ?>

              <div class="log-entry <?php if($change) echo "log-entry--status" ?> <?php if($entry['comment']) echo "log-entry--comment" ?>">
                <?php if($change): ?>
                  Status is <b><?= esc_inner($entry['status']) ?></b><?php if($entry['comment']) echo ", with comment:" ?>
                  <span class="log-entry__meta">
                    <time><?= esc_attr(local_date("Y-m-d H:i", $entry['changed_at'])) ?></time>
                    <?php if($undo && $key == $last): ?>
                      <button class="log-entry__undo" type="button" title="Undo status change" aria-label="Undo status change" x-delete="/todo/undo?id=<?= esc_attr($task['id']) ?>&amp;log_id=<?= esc_attr($entry['id']) ?>" x-target="@document">
                        <i class="fa-solid fa-rotate-left"></i>
                      </button>
                    <?php endif ?>
                  </span>
                <?php endif ?>
                <?php if($entry['comment']): ?>
                  <div class="log-entry__message">
                    <?php if(!$change): ?>
                      <time><?= esc_attr(local_date("Y-m-d H:i", $entry['changed_at'])) ?></time>
                    <?php endif; ?>
                    <p><?= esc_inner($entry['comment']) ?></p>
                  </div>
                <?php endif ?>
                
              </div>
            <?php endforeach ?>
          <?php endif ?>

          <textarea rows="3" name="comment" placeholder="Add a comment..."></textarea>
          <div class="actions">
            <button class="button" type="button" formnovalidate z-key="d" x-delete="/todo/delete?id=<?= esc_attr($task['id']) ?>" z-confirm="Delete this task?">Delete</button>
            <?php
              $latest = $log ? $log[array_key_last($log)] : null;
              $before = count($log) > 1 ? $log[array_key_last($log) - 1] : null;
            ?>
            <div class="actions__group">
              <?php if($latest && !$latest['comment'] &&
                (!$before || $latest['status'] != $before['status'])): ?>
                <button name="amend" value="1">Amend</button>
              <?php endif ?>
              <button>Comment</button>
            </div>
          </div>
        </section>

        <aside>
          <?php \forms\options("status",
              ["backlog", "todo", "wip", "blocked", "done", "nvm"],
              selected: $task['status'],
              capitalize: false) ?>

          <div class="field">
            <label for="open_date">Open</label>
            <span class="datetime-pair">
              <input type="date" id="open_date" name="open_date" value="<?= esc_attr(local_date("Y-m-d", $task['open_at'])) ?>" required>
              <input type="time" name="open_time" lang="<?= TIME_LANG ?>" value="<?= esc_attr(local_date("H:i", $task['open_at'])) ?>" required>
            </span>
          </div>

          <div class="field">
            <label for="due_date">Due</label>
            <span class="datetime-pair">
              <input type="date" id="due_date" name="due_date" value="<?= esc_attr($task['due_at'] ? local_date("Y-m-d", $task['due_at']) : '') ?>">
              <input type="time" name="due_time" lang="<?= TIME_LANG ?>" value="<?= esc_attr($task['due_at'] && !cast_boolean($task['due_all_day']) ? local_date("H:i", $task['due_at']) : '') ?>">
            </span>
          </div>

          <div class="field">
            <label for="expire_date">Expire</label>
            <span class="datetime-pair">
              <input type="date" id="expire_date" name="expire_date" value="<?= esc_attr($task['expire_at'] ? local_date("Y-m-d", $task['expire_at']) : '') ?>">
              <input type="time" name="expire_time" lang="<?= TIME_LANG ?>" value="<?= esc_attr($task['expire_at'] ? local_date("H:i", $task['expire_at']) : '') ?>">
            </span>
          </div>

          <div class="field">
            <label for="recurrence">Repeat</label>
            <input type="text" id="recurrence" name="recurrence" placeholder="FREQ=WEEKLY;BYDAY=MO,WE,FR" value="<?= esc_attr($task['recurrence'] ?? '') ?>">
          </div>

          <?php if($task['recurrence'] && $task['status'] == 'done' && $task['next']): ?>
            <p class="todo-editor__next"><small>Next occurrence <?= esc_inner(local_date(cast_boolean($task['due_all_day']) ? "l j M" : "l j M, H:i", $task['next'])) ?></small></p>
          <?php endif ?>
        </aside>
      </form>
    </main>
  </body>
</html>
