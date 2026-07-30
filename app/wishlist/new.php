<?php

  if(isset($_POST["title"], $_POST["status"], $_POST["urgent"])) {
    $id = \store\put_wish(
      cast_string($_POST['title']),
      cast_string(@$_POST['content']),
      cast_string($_POST['status']),
      cast_boolean($_POST['urgent'])
    );

    $urls = array_map(fn($row) => [...$row, 'price' => cast_float(@$row['price'])],
      unfold($_POST, 'url', 'url'));

    \store\set_wish_urls($id, $urls);
    \store\set_wish_tags($id, $_POST['tags'] ?? []);

    \store\put_audit_log('wishes', $id, "Created wishes/$id.", 'user', operation: 'insert');

    \caldav\mark_resource_changed('wish', $id);

    see_other("/wishlist");
  }

  $url_field = function($row) { ?>
    <input name="url_url[]" type="url" placeholder="url" required value="<?= esc_attr(@$row['url']) ?>" data-value>
    <input name="url_price[]" type="number" min="0" step="0.01" placeholder="price" value="<?= esc_attr(format_price_value(@$row['price'])) ?>">
  <?php };

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
    <main class="main">
      <form id="wishlist-form" class="wishlist-form" x-post="/wishlist/new">
        <div class="actions">
          <a class="button" href="/wishlist" z-key="escape">Cancel</a>
          <button>Save</button>
        </div>

        <input type="hidden" name="status" value="dream">

        <?php title_field() ?>

        <?php tags_field() ?>

        <?php repeat_field("URLs", "URL", [], $url_field) ?>
      </form>
    </main>
  </body>
</html>
