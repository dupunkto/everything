<?php
  $tz = new DateTimeZone(getenv("TIMEZONE") ?: "Europe/Amsterdam");

  if($_SERVER['REQUEST_METHOD'] == "POST") {
    if(!allset($_POST, ["id"]))
      fail("Could not complete request: missing POST data.", status: 400);

    $appt = \store\get_appointment($_POST['id']) or fail("Appointment not found.", status: 404);
    $is_sub = !empty($appt['subscription_id']);
    $owner_color = $is_sub ? $appt['subscription_color'] : $appt['calendar_color'];

    // "Same as owner color" collapses to null so the event tracks the
    // calendar/subscription palette automatically.
    $picked = $_POST['color'] ?? null;
    $color = ($picked and strcasecmp($picked, $owner_color) != 0) ? $picked : null;

    $going = !empty($_POST['going']);
    $circled = !empty($_POST['circled']);

    if($is_sub) {
      \store\update_appointment_annotations($_POST['id'], $color, $going, $circled)
        or fail("Could not save appointment annotations.");
    } else {
      if(!allset($_POST, ["title", "starts_at", "ends_at"]))
        fail("Could not complete request: missing POST data.", status: 400);

      [$sd, $st] = explode("T", $_POST['starts_at']) + [null, null];
      [$ed, $et] = explode("T", $_POST['ends_at']) + [null, null];
      $starts_at = cast_datetime_utc($sd, $st);
      $ends_at = cast_datetime_utc($ed, $et);

      $recurrence = !empty($_POST['repeating']) && !empty($_POST['recurrence'])
        ? $_POST['recurrence'] : null;
      $all_day = !empty($_POST['all_day']);

      \store\update_appointment(
        $_POST['id'],
        $_POST['title'],
        $_POST['content'] ?? null,
        $starts_at,
        $ends_at,
        $_POST['location'] ?? null,
        $appt['meeting'],
        $recurrence,
        $all_day,
        $going,
        $circled,
        $color
      ) or fail("Could not save appointment.");
    }

    $fresh = \store\get_appointment($_POST['id']);
    $_GET['week'] = (new DateTimeImmutable($fresh['starts_at']))->setTimezone($tz)
      ->modify("monday this week")->format("Y-m-d");
    include __DIR__ . "/week.php";
    echo '<script>window.__closePopup && window.__closePopup();</script>';
    exit;
  }

  if(!isset($_GET['id'])) fail("Appointment id is missing.", status: 400);
  $appt = \store\get_appointment($_GET['id']) or fail("Appointment not found.", status: 404);
  $is_sub = !empty($appt['subscription_id']);
  $owner_color = $is_sub ? $appt['subscription_color'] : $appt['calendar_color'];
  $color_value = $appt['color'] ?? $owner_color;

  $start_local = (new DateTimeImmutable($appt['starts_at']))->setTimezone($tz);
  $end_local = (new DateTimeImmutable($appt['ends_at']))->setTimezone($tz);
?>
<form class="appt-form" x-post="/calendar/edit" x-target="#calendar-week">
  <input type="hidden" name="id" value="<?= esc_attr($appt['id']) ?>">

  <input class="appt-color-corner" name="color" type="color" value="<?= esc_attr($color_value) ?>" title="Border color (defaults to the calendar's color)">

  <?php if($is_sub): ?>
    <h3 class="appt-title-readonly"><?= esc_inner($appt['title']) ?></h3>
    <p class="muted">Synced from <?= esc_inner($appt['subscription_title']) ?>. Only the color, circled, and going annotations are editable here.</p>
  <?php else: ?>
    <input class="appt-title-input" name="title" type="text" value="<?= esc_attr($appt['title']) ?>" placeholder="Title" required autofocus>
    <textarea name="content" placeholder="Description"><?= esc_inner($appt['content'] ?? '') ?></textarea>
    <input name="location" type="text" value="<?= esc_attr($appt['location'] ?? '') ?>" placeholder="Location">

    <div class="row">
      <label>
        Starts
        <input name="starts_at" type="datetime-local" value="<?= $start_local->format("Y-m-d\\TH:i") ?>" required>
      </label>
      <label>
        Ends
        <input name="ends_at" type="datetime-local" value="<?= $end_local->format("Y-m-d\\TH:i") ?>" required>
      </label>
    </div>

    <label class="check"><input type="checkbox" name="all_day" <?= $appt['all_day'] ? 'checked' : '' ?>> All day</label>

    <label class="check">
      <input type="checkbox" name="repeating" z-toggle="#edit-recurrence-row" <?= $appt['recurrence'] ? 'checked' : '' ?>>
      Repeating
    </label>
    <div id="edit-recurrence-row" class="recurrence-row" <?= $appt['recurrence'] ? '' : 'hidden' ?>>
      <input name="recurrence" type="text" value="<?= esc_attr($appt['recurrence'] ?? '') ?>" placeholder="cron or number of days">
    </div>
  <?php endif ?>

  <label class="check"><input type="checkbox" name="circled" <?= $appt['circled'] ? 'checked' : '' ?>> Circled</label>
  <label class="check"><input type="checkbox" name="going" <?= $appt['going'] ? 'checked' : '' ?>> Going</label>

  <div class="form-actions">
    <button type="button" x-get="/calendar/popup?id=<?= esc_attr($appt['id']) ?>" x-target="#calendar-popup" x-replace="innerHTML">Cancel</button>
    <button type="submit">Save</button>
  </div>

  <script>
    (() => {
      const form = document.currentScript.closest("form");
      for(const trigger of form.querySelectorAll("input[type=checkbox][z-toggle]")) {
        const target = form.querySelector(trigger.getAttribute("z-toggle"));
        if(!target) continue;
        const sync = () => target.hidden = !trigger.checked;
        trigger.addEventListener("change", sync);
      }
    })();
  </script>
</form>
