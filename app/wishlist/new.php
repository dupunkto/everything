<?php

  if(isset($_POST["title"], $_POST["status"], $_POST["urgent"])) {
    $id = \store\create_wish(
      $_POST['title'],
      $_POST['content'],
      $_POST['status'],
      $_POST['urgent']
    ) or fail("Could not save wish '" . $_POST['title'] . "'.");

    \store\set_wish_urls($id, unfold($_POST, 'url', 'url'));

    http_response_code(303);
    header("Location: /wishlist"); exit;
  }


  $repeat = function($legend, $button, $rows, $render, $confirm = "Are you sure?") { ?>
    <fieldset class="repeat" z-repeat="<?= esc_attr($confirm) ?>">
      <legend><?= esc_inner($legend) ?></legend>
      <div class="repeat__rows">
        <?php foreach($rows as $row): ?>
          <div class="repeat__row"><?php $render($row) ?><button type="button" data-remove>&times;</button></div>
        <?php endforeach ?>
      </div>
      <template><div class="repeat__row"><?php $render([]) ?><button type="button" data-remove>&times;</button></div></template>
      <button type="button" data-add>+ <?= esc_inner($button) ?></button>
    </fieldset>
  <?php };

  $url_field = function($row) { ?>
    <input name="url_url[]" type="url" placeholder="url" required value="<?= esc_attr(@$row['url']) ?>" data-value>
    <input name="url_price[]" type="number" min="0" step="1" placeholder="price" value="<?= esc_attr(@$row['price']) ?>">
  <?php };

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
    <main class="main">
      <form id="wishlist-form" class="wishlist-form" x-post="/wishlist/new">
        <a href="/wishlist" z-key="escape" hidden></a>

        <input type="hidden" name="status" value="dream">

        <label class="title-check">
          <input name="title" type="text" placeholder="Title">
          <span class="title-check__urgent" title="Circle">
            <input type="hidden" name="urgent" value="false">
            <input type="checkbox" name="urgent" value="true" aria-label="Circle">
            <i class="fa-regular fa-square-exclamation"></i>
            <i class="fa-solid fa-square-exclamation"></i>
          </span>
        </label>
        <textarea name="content" placeholder="What are you wishing for...?"></textarea>

        <?php $repeat("URLs", "URL", [], $url_field) ?>

        <button>Save</button>
      </form>
    </main>
  </body>
</html>
