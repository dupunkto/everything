<?php

  if(isset($_POST["title"], $_POST["status"], $_POST["urgent"])) {
    \store\create_wish(
      $_POST['title'],
      $_POST['content'],
      $_POST['status'],
      $_POST['urgent']
    ) or fail("Could not save wish '" . $_POST['title'] . "'.");

    http_response_code(303);
    header("Location: /wishlist"); exit;
  }

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Wishlist</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/wishlist.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main main--semi-wide">
      <form id="wishlist-form" class="wishlist-form" x-post="/wishlist/new">
        <input type="hidden" name="status" value="dream">

        <input name="title" type="text" placeholder="Title">
        <textarea name="content" placeholder="What are you wishing for...?"></textarea>

        <label class="check">
          <input type="hidden" name="urgent" value="false">
          <input type="checkbox" id="urgent" name="urgent" value="true"> circle
        </label>

        <button>Save</button>
      </form>
    </main>
  </body>
</html>
