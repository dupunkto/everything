<?php

  if(isset($_POST['id'])) {
    \store\update_wish(
      $_POST['id'],
      $_POST['title'],
      $_POST['content'],
      $_POST['urgent']
    ) or fail("Could not update wish.");

    \store\set_wish_status($_POST['id'], $_POST['status'])
      or fail("Could not update wish status.");

    if(isset($_POST['close'])) {
      http_response_code(303);
      header("Location: /wishlist"); exit;
    }
  }

  $wish = \store\get_wish(@$_GET['id'] ?: @$_POST['id'])
    or fail("Wish not found.", status: 404);

  $status_for = fn($target) => $wish['status'] == $target ? "dream" : $target;

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Wishlist</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/wishlist.css">
    <script src="<?= CANONICAL ?>/client/circle.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main main--semi-wide">
      <form id="wishlist-editor" class="wishlist-editor" x-post="/wishlist/edit" x-on="change" x-target="@document">
        <input type="hidden" name="id" value="<?= esc_attr($wish['id']) ?>">

        <button type="button" z-key="s" z-set=".wishlist-editor [name=status]" value="<?= $status_for('nvm') ?>" hidden></button>
        <button type="button" z-key="c" z-set=".wishlist-editor [name=status]" value="<?= $status_for('bought') ?>" hidden></button>
        <button type="submit" name="close" value="1" z-key="escape mod+enter" hidden></button>

        <div class="title-row">
          <span class="circled-field" z-circle>
            <?php if(cast_boolean($wish['urgent'])) circle() ?>
            <input name="title" type="text" placeholder="Title" value="<?= esc_attr($wish['title']) ?>">
          </span>
          <?php \forms\options("status",
            ["dream", "bought", "nvm"], selected: $wish['status'], capitalize: false) ?>
        </div>

        <textarea name="content" placeholder="What are you wishing for...?"><?= esc_inner($wish['content']) ?></textarea>

        <div class="actions">
          <a class="button" href="/wishlist/delete?id=<?= esc_attr($wish['id']) ?>" z-confirm="Delete this wish?">Delete</a>

          <label class="check">
            <input type="hidden" name="urgent" value="false">
            <input type="checkbox" id="urgent" name="urgent" value="true" <?php if(filter_var($wish['urgent'], FILTER_VALIDATE_BOOLEAN)) echo "checked" ?>> Circle
          </label>
        </div>
      </form>
    </main>
  </body>
</html>
