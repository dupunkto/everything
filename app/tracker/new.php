<?php

  if(isset($_POST['start_date'], $_POST['start_time'], $_POST['end_date'], $_POST['end_time'])) {
    $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
    $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

    $id = \store\put_timing(
      $_POST['description'],
      $starts_at,
      $ends_at
    ) or fail("Could not save timing from $starts_at to $ends_at with description '" . $_POST['description'] . "'.");

    \store\set_timing_tags($id, $_POST['tags'] ?? []);
    \store\put_log('timings', $id, "Created timing.", 'user')
      or fail("Could not create audit entry.");

    include __DIR__ . "/listing.php"; exit;
  }

?>
<form id="tracker-form" class="tracker-form" x-post="/tracker/new" x-target="#tracker-listing" x-refresh="#tracker-new" z-timer>
  <textarea name="description" placeholder="What have you been up to?"></textarea>

  <?php tags_field() ?>

  <div class="tracker-form__timer">
    <button type="button" class="tracker-form__duration" data-duration>0:00:00</button>
    <div class="tracker-time-popup" data-time-popup hidden>
      <label>
        Start
        <span class="tracker-time-popup__inputs">
          <input name="start_date" type="date" value="<?= local_date("Y-m-d") ?>">
          <input name="start_time" type="time" step="1">
        </span>
      </label>
      <label>
        End
        <span class="tracker-time-popup__inputs">
          <input name="end_date" type="date" value="<?= local_date("Y-m-d") ?>">
          <input name="end_time" type="time" step="1">
        </span>
      </label>
    </div>
  </div>

  <button type="button" class="tracker-form__record" data-record>▶</button>
</form>
