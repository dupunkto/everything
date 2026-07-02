<?php
  $tz = new DateTimeZone(getenv("TIMEZONE") ?: "Europe/Amsterdam");

  if($_SERVER['REQUEST_METHOD'] == "POST") {
    if(!allset($_POST, ["calendar_id", "title", "start_date", "start_time", "end_date", "end_time"]))
      fail("Could not complete request: missing POST data.", status: 400);

    $calendar = \store\get_calendar($_POST['calendar_id'])
      or fail("Calendar not found.", status: 404);

    $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
    $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

    // The color input is always populated (no way to "blank" a native color
    // picker). We treat "same as calendar color" as the canonical no-override
    // state, mirroring how the JS resets the picker on calendar change.
    $picked = $_POST['color'] ?? null;
    $color = ($picked and strcasecmp($picked, $calendar['color']) != 0) ? $picked : null;

    $recurrence = !empty($_POST['repeating']) && !empty($_POST['recurrence'])
      ? $_POST['recurrence'] : null;
    $all_day = !empty($_POST['all_day']);
    $going = !empty($_POST['going']);
    $circled = !empty($_POST['circled']);

    \store\create_appointment(
      $_POST['calendar_id'],
      $_POST['title'],
      $_POST['content'] ?? null,
      $starts_at,
      $ends_at,
      $_POST['location'] ?? null,
      null, // meeting only flows in via subscription sync
      $recurrence,
      $all_day,
      $going,
      $circled,
      $color
    ) or fail("Could not create appointment.");

    $_GET['week'] = (new DateTimeImmutable($_POST['start_date'], $tz))
      ->modify("monday this week")->format("Y-m-d");
    include __DIR__ . "/week.php";
    echo '<script>window.__closePopup && window.__closePopup();</script>';
    exit;
  }

  // GET — render the form. Inputs come from the click handler in calendar.php.
  $date = $_GET['date'] ?? (new DateTimeImmutable("now", $tz))->format("Y-m-d");
  $minute = max(0, min(1425, (int)($_GET['minute'] ?? 540)));
  $start_local = (new DateTimeImmutable($date, $tz))->setTime(intdiv($minute, 60), $minute % 60);
  $end_local = $start_local->modify("+1 hour");

  $calendars = \store\list_calendars() ?: [];

  if(empty($calendars)):
?>
  <p class="empty">No calendars yet &mdash; <a href="<?= CANONICAL ?>/settings/calendars">create one</a> first.</p>
<?php
    return;
  endif;

  $default_color = $calendars[0]['color'];
?>
<form class="appt-form" x-post="/calendar/new" x-target="#calendar-week">
  <input class="appt-color-corner" name="color" type="color" value="<?= esc_attr($default_color) ?>" title="Border color (defaults to the calendar's color)">
  <input class="appt-title-input" name="title" type="text" placeholder="Title" required autofocus>

  <?php if(count($calendars) > 1): ?>
    <select name="calendar_id" required>
      <?php foreach($calendars as $c): ?>
        <option value="<?= esc_attr($c['id']) ?>" data-color="<?= esc_attr($c['color']) ?>">
          <?= esc_inner($c['title']) ?><?php if($c['subtitle']) echo " &mdash; " . esc_inner($c['subtitle']) ?>
        </option>
      <?php endforeach ?>
    </select>
  <?php else: ?>
    <input type="hidden" name="calendar_id" value="<?= esc_attr($calendars[0]['id']) ?>">
  <?php endif ?>

  <textarea name="content" placeholder="Description"></textarea>
  <input name="location" type="text" placeholder="Location">

  <label class="field">
    Starts
    <span class="datetime-pair">
      <input name="start_date" type="date" value="<?= $start_local->format("Y-m-d") ?>" required>
      <input name="start_time" type="time" value="<?= $start_local->format("H:i") ?>" required>
    </span>
  </label>
  <label class="field">
    Ends
    <span class="datetime-pair">
      <input name="end_date" type="date" value="<?= $end_local->format("Y-m-d") ?>" required>
      <input name="end_time" type="time" value="<?= $end_local->format("H:i") ?>" required>
    </span>
  </label>

  <label class="check"><input type="checkbox" name="all_day"> All day</label>

  <label class="check">
    <input type="checkbox" name="repeating" z-toggle="#new-recurrence-row">
    Repeating
  </label>
  <div id="new-recurrence-row" class="recurrence-row" hidden>
    <input name="recurrence" type="text" placeholder="cron or number of days">
  </div>

  <label class="check"><input type="checkbox" name="circled"> Circled</label>
  <label class="check"><input type="checkbox" name="going" checked> Going</label>

  <div class="form-actions">
    <button type="button" onclick="window.__closePopup()">Cancel</button>
    <button type="submit">Save</button>
  </div>

  <script>
    // zhtml.js wishlist:
    //   - z-toggle on a checkbox should flip [hidden] on change (this loop).
    //   - z-mirror="<select> @data-color" → copy the chosen option's
    //     data-color into the host's value when the source fires change.
    // Until that exists, both behaviors are stitched together here.
    (() => {
      const form = document.currentScript.closest("form");

      for(const trigger of form.querySelectorAll("input[type=checkbox][z-toggle]")) {
        const target = form.querySelector(trigger.getAttribute("z-toggle"));
        if(!target) continue;
        const sync = () => target.hidden = !trigger.checked;
        trigger.addEventListener("change", sync);
        sync();
      }

      const select = form.querySelector("select[name=calendar_id]");
      const colorInput = form.querySelector("input[name=color]");
      if(select && colorInput) {
        select.addEventListener("change", () => {
          const opt = select.selectedOptions[0];
          if(opt && opt.dataset.color) colorInput.value = opt.dataset.color;
        });
      }
    })();
  </script>
</form>
