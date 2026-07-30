<?php

  if(isset($_POST['id'])) {
    $wish = \store\get_wish($_POST['id']) or fail("Wish not found.", status: 404);
    $fields = \core\diff([...$wish, 'tags' => \store\list_wish_tag_ids($_POST['id'])],
      title: cast_string($_POST['title']), content: cast_string($_POST['content']),
      urgent: cast_boolean($_POST['urgent']), added_at: cast_datetime_utc($_POST['date'], $_POST['time']),
      status: $_POST['status'], comment: cast_string(@$_POST['comment']),
      tags: $_POST['tags'] ?? [],
      urls: array_map(fn($row) => [...$row, 'price' => cast_float(@$row['price'])],
        unfold($_POST, 'url', 'url')));

    \store\update_wish(
      $_POST['id'],
      cast_string($_POST['title']),
      cast_string($_POST['content']),
      cast_boolean($_POST['urgent']),
      cast_datetime_utc($_POST['date'], $_POST['time'])
    );

    \store\set_wish_status($_POST['id'], $_POST['status'], cast_string(@$_POST['comment']));

    \store\set_wish_urls($_POST['id'],
      array_map(fn($row) => [...$row, 'price' => cast_float(@$row['price'])],
        unfold($_POST, 'url', 'url')));
    \store\set_wish_tags($_POST['id'], $_POST['tags'] ?? []);

    \store\put_audit_log('wishes', $_POST['id'],
      "Updated [" . join(", ", $fields) . "] for wishes/{$_POST['id']}.", 'user');

    \caldav\mark_resource_changed('wish', $_POST['id']);

    if(isset($_POST['close'])) {
      see_other("/wishlist");
    }
  }

  $wish = \store\get_wish(@$_GET['id'] ?: @$_POST['id'])
    or fail("Wish not found.", status: 404);

  $status_for = fn($target) => $wish['status'] == $target ? "dream" : $target;

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
      <form id="wishlist-editor" class="wishlist-editor" x-post="/wishlist/edit" x-on="change" x-target="@document">
        <input type="hidden" name="id" value="<?= esc_attr($wish['id']) ?>">

        <button type="button" z-key="s" z-set=".wishlist-editor [name=status]" value="<?= $status_for('nvm') ?>" hidden></button>
        <button type="button" z-key="c" z-set=".wishlist-editor [name=status]" value="<?= $status_for('bought') ?>" hidden></button>
        <button type="submit" name="close" value="1" z-key="escape mod+enter" hidden></button>

        <div class="title-row">
          <?php title_field($wish['title'], filter_var($wish['urgent'], FILTER_VALIDATE_BOOLEAN), autofocus: false) ?>
          <?php \forms\options("status",
            ["dream", "bought", "nvm"], selected: $wish['status'], capitalize: false) ?>
        </div>

        <?php tags_field(\store\list_wish_tags($wish['id'])) ?>

        <?php if($wish['status'] == 'nvm' && $wish['comment']): ?>
          <p class="status-comment"><?= esc_inner($wish['comment']) ?></p>
        <?php endif ?>

        <?php repeat_field("URLs", "URL", $wish['urls'], $url_field) ?>

        <label class="wishlist-editor-textarea">
          Description
          <textarea name="content" placeholder="What are you wishing for...?" rows="3"><?= esc_inner($wish['content']) ?></textarea>
        </label>

        <div class="actions">
          <button class="button" type="button" formnovalidate z-key="d" x-delete="/wishlist/delete?id=<?= esc_attr($wish['id']) ?>" z-confirm="Delete this wish?">Delete</button>
          <div class="field wishlist-editor__added">
            <label for="date">added on</label>
            <span class="datetime-pair">
              <input type="date" id="date" name="date" value="<?= esc_attr(local_date("Y-m-d", $wish['added_at'])) ?>" required>
              <input type="time" name="time" lang="<?= TIME_LANG ?>" value="<?= esc_attr(local_date("H:i", $wish['added_at'])) ?>" required>
            </span>
          </div>
        </div>
      </form>
    </main>
  </body>
</html>
