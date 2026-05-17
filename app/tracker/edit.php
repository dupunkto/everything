<?php
  if(allset($_POST, ["id", "description", "starts_at", "ends_at"])) {
    \store\update_timing($_POST['id'], $_POST['description'], $_POST['starts_at'], $_POST['ends_at'], null)
      or fail("Could not save timing " . $_POST['id'] . " from " . $_POST['starts_at'] . " to " . $_POST['ends_at'] . " with description '" . $_POST['description'] . "'.");

    include __DIR__ . "/listing.php"; exit;
  }

  if(!isset($_GET['id'])) fail("Timing is missing.");
  $timing = \store\get_timing($_GET['id']) or fail("Timing not found.");
?>
<li>
  <form id="tracker-editor" x-post="/tracker/edit" x-target="#tracker-listing">
    <input name="id" type="hidden" value="<?= $_GET['id'] ?>">
    <textarea name="description" placeholder="What were you up to?" autofocus><?= esc_inner($timing['description']) ?></textarea>
    <div class="col">
      <label>
        Start
        <input name="starts_at" type="datetime-local" step="1" value="<?= cast_datetime_local($timing['starts_at']) ?>" />
      </label>
      <label>
        End
        <input name="ends_at" type="datetime-local" step="1" value="<?= cast_datetime_local($timing['ends_at']) ?>" />
      </label>
    </div>
    <div class="col">
      <button type="submit">Save</button>
      <button x-get="/tracker/listing" x-target="#tracker-listing">Cancel</button>
    </div>
  </form>
</li>