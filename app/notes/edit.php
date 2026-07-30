<?php

  if(isset($_POST['id'])) {
    $note = \store\get_note($_POST['id']) or fail("Note not found.", status: 404);
    $fields = \core\diff([...$note, 'tags' => \store\list_note_tag_ids($_POST['id'])],
      title: $_POST['title'], content: $_POST['content'],
      written_at: cast_datetime_utc($_POST['date'], $_POST['time']), tags: $_POST['tags'] ?? []);

    \store\update_note(
      $_POST['id'],
      $_POST['title'],
      $_POST['content'],
      cast_datetime_utc($_POST['date'], $_POST['time'])
    );

    \store\set_note_tags($_POST['id'], $_POST['tags'] ?? []);
    \store\put_audit_log('notes', $_POST['id'],
      "Updated [" . join(", ", $fields) . "] for notes/{$_POST['id']}.", 'user');

    if(isset($_POST['close'])) {
      see_other("/notes");
    }
  }

  $note = \store\get_note(@$_GET['id'] ?: @$_POST['id'])
    or fail("Note not found.", status: 404);

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Notes</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/notes.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <form id="note-editor" class="note-editor" x-post="/notes/edit" x-on="change" x-target="@document">
        <input type="hidden" name="id" value="<?= esc_attr($note['id']) ?>">
        <button type="submit" name="close" value="1" z-key="escape mod+enter" hidden></button>

        <input name="title" type="text" placeholder="Title" value="<?= esc_attr($note['title']) ?>">
        <?php tags_field(\store\list_note_tags($note['id'])) ?>
        <textarea name="content" placeholder="What do you want to remember?"><?= esc_inner($note['content']) ?></textarea>

        <div class="actions">
          <button class="button" type="button" formnovalidate z-key="d" x-delete="/notes/delete?id=<?= esc_attr($note['id']) ?>" z-confirm="Delete this note?">Delete</button>
          <div class="field note-editor__saved">
            <label for="date">Written at</label>
            <span class="datetime-pair">
              <input type="date" id="date" name="date" value="<?= esc_attr(local_date("Y-m-d", $note['written_at'])) ?>" required>
              <input type="time" name="time" lang="<?= TIME_LANG ?>" value="<?= esc_attr(local_date("H:i", $note['written_at'])) ?>" required>
            </span>
          </div>
        </div>
      </form>
    </main>
  </body>
</html>
