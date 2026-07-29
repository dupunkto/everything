<?php

  if(isset($_POST['id'])) {
    $bookmark = \store\get_bookmark($_POST['id']) or fail("Bookmark not found.", status: 404);
    $fields = \core\diff([...$bookmark, 'tags' => \store\list_bookmark_tag_ids($_POST['id'])],
      label: cast_string(@$_POST['label']),
      url: cast_string($_POST['url']),
      note: cast_string(@$_POST['note']),
      saved_at: cast_datetime_utc($_POST['date'], $_POST['time']),
      tags: $_POST['tags'] ?? []);

    \store\update_bookmark(
      $_POST['id'],
      cast_string(@$_POST['label']),
      cast_string($_POST['url']),
      cast_string(@$_POST['note']),
      cast_datetime_utc($_POST['date'], $_POST['time'])
    );

    \store\set_bookmark_tags($_POST['id'], $_POST['tags'] ?? []);
    \store\put_audit_log('bookmarks', $_POST['id'],
      "Updated [" . join(", ", $fields) . "] for bookmarks/{$_POST['id']}.", 'user');

    if(isset($_POST['close'])) {
      http_response_code(303);
      header("Location: /bookmarks"); exit;
    }
  }

  $bookmark = \store\get_bookmark(@$_GET['id'] ?: @$_POST['id'])
    or fail("Bookmark not found.", status: 404);

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
      <form id="bookmark-editor" class="bookmark-editor" x-post="/bookmarks/edit" x-on="change" x-target="@document">
        <input type="hidden" name="id" value="<?= esc_attr($bookmark['id']) ?>">
        <button type="submit" name="close" value="1" z-key="escape mod+enter" hidden></button>

        <input name="label" type="text" placeholder="Title" value="<?= esc_attr($bookmark['label']) ?>">
        <div class="bookmark-editor__url">
          <input name="url" type="url" placeholder="URL" required value="<?= esc_attr($bookmark['url']) ?>">
          <a class="button" href="<?= esc_attr($bookmark['url']) ?>" z-key="g">&rarr;</a>
        </div>

        <?php tags_field(\store\list_bookmark_tags($bookmark['id'])) ?>

        <label>
          Note
          <textarea name="note" rows="4"><?= esc_inner($bookmark['note']) ?></textarea>
        </label>

        <div class="actions">
          <button class="button" type="button" formnovalidate z-key="d" x-delete="/bookmarks/delete?id=<?= esc_attr($bookmark['id']) ?>" z-confirm="Delete this bookmark?">Delete</button>
          <div class="field bookmark-editor__saved">
            <label for="date">Saved on</label>
            <span class="datetime-pair">
              <input type="date" id="date" name="date" value="<?= esc_attr(local_date("Y-m-d", $bookmark['saved_at'])) ?>" required>
              <input type="time" name="time" lang="<?= TIME_LANG ?>" value="<?= esc_attr(local_date("H:i", $bookmark['saved_at'])) ?>" required>
            </span>
          </div>
        </div>
      </form>
    </main>
  </body>
</html>
