<?php

  if(isset($_POST["id"], $_POST["description"], $_POST["start_date"], $_POST["start_time"], $_POST["end_date"], $_POST["end_time"])) {
    $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
    $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

    \store\update_timing($_POST['id'], $_POST['description'], $starts_at, $ends_at, null)
      or fail("Could not save timing " . $_POST['id'] . " from " . $starts_at . " to " . $ends_at . " with description '" . $_POST['description'] . "'.");

    \store\set_timing_tags($_POST['id'], $_POST['tags'] ?? []);
    \store\put_log('timings', $_POST['id'], "Updated timing.", 'user')
      or fail("Could not create audit entry.");

    include __DIR__ . "/listing.php"; exit;
  }

  $timing = \store\get_timing($_GET['id']) or fail("Timing not found.", status: 404);
  $starts = new DateTime(cast_datetime_local($timing['starts_at']));
  $ends = new DateTime(cast_datetime_local($timing['ends_at']));
  $show_dates = $starts->format('Y-m-d') != $ends->format('Y-m-d');

?>
<form class="tracker-editor" x-post="/tracker/edit" x-on="input" x-target="#tracker-listing">
  <input name="id" type="hidden" value="<?= esc_attr($_GET['id']) ?>">
  <button type="button" class="tracker-editor__close" data-close aria-label="Close">×</button>

  <textarea name="description" placeholder="What were you up to?" rows="2" autofocus><?= esc_inner($timing['description']) ?></textarea>

  <?php tags_field(\store\get_timing_tags($_GET['id'])) ?>

  <div class="tracker-editor__times">
    <label>
      Start
      <span class="tracker-editor__datetime">
        <input name="start_time" type="time" step="1" value="<?= $starts->format('H:i:s') ?>">
        <input class="tracker-editor__date" name="start_date" type="date" value="<?= $starts->format('Y-m-d') ?>" <?= $show_dates ? '' : 'hidden' ?>>
      </span>
    </label>
    <label>
      End
      <span class="tracker-editor__datetime">
        <span class="tracker-editor__time">
          <input name="end_time" type="time" step="1" value="<?= $ends->format('H:i:s') ?>">
          <?php if(!$show_dates): ?>
            <button type="button" data-show-dates aria-label="Show dates">+</button>
          <?php endif ?>
        </span>
        <input class="tracker-editor__date" name="end_date" type="date" value="<?= $ends->format('Y-m-d') ?>" <?= $show_dates ? '' : 'hidden' ?>>
      </span>
    </label>
  </div>

  <button type="button" class="tracker-editor__delete" data-delete="<?= esc_attr($_GET['id']) ?>">Delete</button>
</form>
