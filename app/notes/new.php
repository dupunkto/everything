<?php

  if(isset($_POST["title"], $_POST["content"])) {
    $id = \store\put_note($_POST['title'], $_POST['content'], gmdate('c'))
      or fail("Could not save note '" . $_POST['title'] . "'.");

    \store\set_note_tags($id, $_POST['tags'] ?? []);
    \store\put_log('notes', $id, "Created note.", 'user', operation: 'insert')
      or fail("Could not create audit entry.");

    http_response_code(303);
    header("Location: /notes"); exit;
  }

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
      <form id="note-form" class="note-form" x-post="/notes/new">
        <div class="actions">
          <a class="button" href="/notes" z-key="escape">Cancel</a>
          <button>Save</button>
        </div>

        <input name="title" type="text" placeholder="Title" autofocus>
        <?php tags_field() ?>
        <textarea name="content" placeholder="What's on your mind?"></textarea>
      </form>
    </main>
  </body>
</html>
