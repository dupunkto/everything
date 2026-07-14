<?php

  if(isset($_POST['start_date'], $_POST['start_time'], $_POST['end_date'], $_POST['end_time'])) {
    $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
    $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

    \store\create_timing(
      $_POST['description'],
      $starts_at,
      $ends_at
    ) or fail("Could not save timing from $starts_at to $ends_at with description '" . $_POST['description'] . "'.");

    include __DIR__ . "/listing.php"; exit;
  }

?>
<form id="tracker-form" class="tracker-form" x-post="/tracker/new" x-target="#tracker-listing" z-timer>
  <textarea name="description" placeholder="What have you been up to?" autofocus></textarea>
  <div class="tracker-form__col">
    <label>
      Start
      <input name="start_date" type="date" value="<?= local_date("Y-m-d") ?>">
      <input name="start_time" type="time" step="1">
    </label>
    <label>
      End
      <input name="end_date" type="date" value="<?= local_date("Y-m-d") ?>">
      <input name="end_time" type="time" step="1">
    </label>
  </div>
  <button type="button" data-record>Sorry, the tracker could not be loaded.</button>
  <button type="submit" data-save hidden>Save</button>
</form>
