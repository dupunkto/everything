<?php

  if(isset($_POST["title"], $_POST["content"])) {
    \store\create_note($_POST['title'], $_POST['content'])
      or fail("Could not save note '" . $_POST['title'] . "'.");

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
        <textarea name="content" placeholder="What's on your mind?"></textarea>
      </form>
    </main>
  </body>
</html>
