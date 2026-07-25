<?php

  if(isset($_POST['url'])) {
    $url = cast_string($_POST['url']) or fail("URL is required.");
    $meta = \bookmarks\fetch_meta($url);

    $id = \store\put_bookmark(
      $url,
      $meta['label'],
      cast_string(@$_POST['note']),
      $meta['favicon'],
      gmdate('c')
    ) or fail("Could not save bookmark.");

    \store\set_bookmark_tags($id, $_POST['tags'] ?? []);
    \store\put_audit_log('bookmarks', $id, "Created bookmarks/$id.", 'user', operation: 'insert')
      or fail("Could not create audit entry.");

    http_response_code(303);
    header("Location: /bookmarks"); exit;
  }

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Bookmarks</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/bookmarks.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <form id="bookmark-form" class="bookmark-form" x-post="/bookmarks/new">
        <div class="actions">
          <a class="button" href="/bookmarks" z-key="escape">Cancel</a>
          <button>Save</button>
        </div>

        <input name="url" type="url" placeholder="URL" required autofocus>

        <?php tags_field() ?>

        <label>
          Note
          <textarea name="note" rows="4"></textarea>
        </label>
      </form>
    </main>
  </body>
</html>
