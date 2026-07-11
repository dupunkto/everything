<?php
  if(isset($_POST["calendar_id"], $_POST["title"], $_POST["start_date"], $_POST["start_time"], $_POST["end_date"], $_POST["end_time"])) {
    $calendar = \store\get_calendar($_POST['calendar_id'])
      or fail("Calendar not found.", status: 404);
    
    $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
    $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

    // The color input is always populated (no way to "blank" a native color
    // picker). We treat "same as calendar" as NULL.
    $picked = @$_POST['color'];
    $same_as_calendar = $picked and strcasecmp($picked, $calendar['color']) == 0;
    $color = $same_as_calendar ? null : $picked;

    $recurrence = !empty($_POST['repeating'])
      && !empty($_POST['recurrence']) ? $_POST['recurrence'] : null;

    $all_day = !empty($_POST['all_day']);
    $going = !empty($_POST['going']);
    $urgent = !empty($_POST['urgent']);

    // Travel time is only stored when the toggle is on; the minute inputs are
    // optional, an empty one means no band on that side. Clamped to >= 0 to
    // satisfy the schema constraint.
    $travel = !empty($_POST['travel']);
    $travel_before = $travel ? max(0, (int) ($_POST['travel_before'] ?? 0)) : 0;
    $travel_after = $travel ? max(0, (int) ($_POST['travel_after'] ?? 0)) : 0;

    \store\create_appointment(
      $_POST['calendar_id'],
      $_POST['title'],
      @$_POST['content'],
      $starts_at,
      $ends_at,
      @$_POST['location'],
      @$_POST['meeting'],
      $recurrence,
      $all_day,
      $going,
      $urgent,
      $color,
      $travel_before,
      $travel_after
    ) or fail("Could not create appointment.");

    $_GET['date'] = $_POST['start_date'];

    include __DIR__ . "/week.php"; exit;
  }

  $calendars = \store\list_calendars();
  $default_color = $calendars[0]['color'];
?>
<form id="calendar-form" x-post="/calendar/new" x-target="#calendar-week">
  <input type="text" name="title" placeholder="Title" required autofocus>
  <input type="color" name="color" value="<?= $default_color ?>">

  <?php if(count($calendars) > 1): ?>
    <select name="calendar_id" required>
      <?php foreach($calendars as $c): ?>
        <option value="<?= esc_attr($c['id']) ?>" data-color="<?= esc_attr($c['color']) ?>">
          <?= esc_inner($c['title']) ?><?php if($c['subtitle']) echo " (" . esc_inner($c['subtitle']) . ")" ?>
        </option>
      <?php endforeach ?>
    </select>
  <?php else: ?>
    <input type="hidden" name="calendar_id" value="<?= esc_attr($calendars[0]['id']) ?>">
  <?php endif ?>

  <textarea name="content" placeholder="Description"></textarea>
  <input name="location" type="text" placeholder="Location">
  <input name="meeting" type="text" placeholder="Meeting URL">

  <label class="field">
    Starts
    <span class="datetime-pair">
      <input name="start_date" type="date" required>
      <input name="start_time" type="time" required>
    </span>
  </label>
  <label class="field">
    Ends
    <span class="datetime-pair">
      <input name="end_date" type="date" required>
      <input name="end_time" type="time" required>
    </span>
  </label>

  <label class="check"><input type="checkbox" name="all_day"> All day</label>

  <label class="check">
    <input type="checkbox" name="repeating" z-toggle="#calendar-new-recurrence">
    Repeating
  </label>

  <div id="calendar-new-recurrence" hidden>
    <input name="recurrence" type="text" placeholder="cron or number of days">
  </div>

  <label class="check">
    <input type="checkbox" name="travel" z-toggle="#calendar-new-travel">
    Travel time
  </label>

  <div id="calendar-new-travel" hidden>
    <label class="field">
      Before
      <input name="travel_before" type="number" min="0" step="5" placeholder="minutes">
    </label>
    <label class="field">
      After
      <input name="travel_after" type="number" min="0" step="5" placeholder="minutes">
    </label>
  </div>

  <label class="check"><input type="checkbox" name="urgent"> Circle</label>
  <label class="check"><input type="checkbox" name="going" checked> Going</label>

  <button type="submit">Save</button>

  <script>
    // dingen om aan zhtml toe te voegen
    //   - z-toggle op toggle om ander element visibility te togglen
    //   - z-mirror op input om value naar andere input te copyen
    (() =>{
      const form = document.currentScript.closest("form");

      for(const trigger of form.querySelectorAll("input[type=checkbox][z-toggle]")) {
        form.querySelectorAll(trigger.getAttribute("z-toggle")).forEach(target => {
          trigger.addEventListener("change", () => target.hidden = !target.hidden);
        });
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