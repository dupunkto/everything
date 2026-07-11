<?php
  $appointment = \store\get_appointment(@$_POST['id'] ?? @$_GET['id'])
    or fail("Appointment not found.", status: 404);

  // Subscription appointments are owned by their feed; only the user's
  // annotations (going, urgent, travel) may be edited. Calendar appointments
  // are fully editable. Colour always inherits from the calendar/subscription.
  $is_subscription = !empty($appointment['subscription_id']);
  $source_color = $appointment['calendar_color'] ?? $appointment['subscription_color'];

  if(isset($_POST['id'])) {
    $calendar_id = @$_POST['calendar_id'] ?: null;

    $going = !empty($_POST['going']);
    $urgent = !empty($_POST['urgent']);

    $travel = !empty($_POST['travel']);
    $travel_before = $travel ? max(0, (int) ($_POST['travel_before'] ?? 0)) : 0;
    $travel_after = $travel ? max(0, (int) ($_POST['travel_after'] ?? 0)) : 0;

    if($is_subscription) {
      \store\update_appointment_meta(
        $appointment['id'], $going, $urgent, $travel_before, $travel_after
      ) or fail("Could not update appointment.");
    }
    else {
      $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
      $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

      $recurrence = !empty($_POST['repeating'])
        && !empty($_POST['recurrence']) ? $_POST['recurrence'] : null;

      $all_day = !empty($_POST['all_day']);

      \store\update_appointment(
        $appointment['id'],
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
        $travel_before,
        $travel_after,
        $calendar_id
      ) or fail("Could not update appointment.");
    }

    // The caller re-fetches the week itself (preserving its filters), so this
    // endpoint has nothing to render back.
    http_response_code(204); exit;
  }

  $timezone = getenv("TIMEZONE") ?: "Europe/Amsterdam";

  $starts_local = new DateTime(cast_datetime_local($appointment['starts_at'], $timezone));
  $ends_local = new DateTime(cast_datetime_local($appointment['ends_at'], $timezone));

  $repeating = !empty($appointment['recurrence']);
  $travel_on = (int) $appointment['travel_before'] || (int) $appointment['travel_after'];

  $calendars = $is_subscription ? [] : (\store\list_calendars() ?: []);

  $bool = fn($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN);
?>
<form id="calendar-edit">
  <input type="hidden" name="id" value="<?= esc_attr($appointment['id']) ?>">

  <div class="calendar-editor__head">
    <input type="color" value="<?= esc_attr($source_color) ?>" tabindex="-1" readonly>
    <input type="text" name="title" placeholder="Title" value="<?= esc_attr($appointment['title']) ?>"
      <?= $is_subscription ? 'readonly' : 'required autofocus' ?>>
  </div>

  <?php if(!$is_subscription): ?>
    <?php if(count($calendars) > 1): ?>
      <select name="calendar_id" required>
        <?php foreach($calendars as $c): ?>
          <option value="<?= esc_attr($c['id']) ?>" data-color="<?= esc_attr($c['color']) ?>" <?= $c['id'] == $appointment['calendar_id'] ? 'selected' : '' ?>>
            <?= esc_inner($c['title']) ?><?php if($c['subtitle']) echo " (" . esc_inner($c['subtitle']) . ")" ?>
          </option>
        <?php endforeach ?>
      </select>
    <?php endif ?>

    <textarea name="content" placeholder="Description"><?= esc_inner($appointment['content'] ?? '') ?></textarea>
    <input name="location" type="text" placeholder="Location" value="<?= esc_attr($appointment['location'] ?? '') ?>">
    <input name="meeting" type="text" placeholder="Meeting URL" value="<?= esc_attr($appointment['meeting'] ?? '') ?>">

    <label class="field">
      Starts
      <span class="datetime-pair">
        <input name="start_date" type="date" value="<?= $starts_local->format('Y-m-d') ?>" required>
        <input name="start_time" type="time" value="<?= $starts_local->format('H:i') ?>" required>
      </span>
    </label>
    <label class="field">
      Ends
      <span class="datetime-pair">
        <input name="end_date" type="date" value="<?= $ends_local->format('Y-m-d') ?>" required>
        <input name="end_time" type="time" value="<?= $ends_local->format('H:i') ?>" required>
      </span>
    </label>

    <label class="check"><input type="checkbox" name="all_day" <?= $bool($appointment['all_day']) ? 'checked' : '' ?>> All day</label>

    <label class="check">
      <input type="checkbox" name="repeating" z-toggle="#calendar-edit-recurrence" <?= $repeating ? 'checked' : '' ?>>
      Repeating
    </label>

    <div id="calendar-edit-recurrence" <?= $repeating ? '' : 'hidden' ?>>
      <input name="recurrence" type="text" placeholder="cron or number of days" value="<?= esc_attr($appointment['recurrence'] ?? '') ?>">
    </div>
  <?php endif ?>

  <label class="check"><input type="checkbox" name="urgent" <?= $bool($appointment['urgent']) ? 'checked' : '' ?>> Circle</label>
  <label class="check"><input type="checkbox" name="going" <?= $bool($appointment['going']) ? 'checked' : '' ?>> Going</label>

  <label class="check">
    <input type="checkbox" name="travel" z-toggle="#calendar-edit-travel" <?= $travel_on ? 'checked' : '' ?>>
    Travel time
  </label>

  <div id="calendar-edit-travel" <?= $travel_on ? '' : 'hidden' ?>>
    <label class="field">
      Before
      <input name="travel_before" type="number" min="0" step="5" placeholder="minutes" value="<?= (int) $appointment['travel_before'] ?: '' ?>">
    </label>
    <label class="field">
      After
      <input name="travel_after" type="number" min="0" step="5" placeholder="minutes" value="<?= (int) $appointment['travel_after'] ?: '' ?>">
    </label>
  </div>

  <?php if(!$is_subscription): ?>
    <button type="button" data-delete>Delete</button>
  <?php endif ?>

  <script>
    (() => {
      const form = document.currentScript.closest("form");

      for(const trigger of form.querySelectorAll("input[type=checkbox][z-toggle]")) {
        form.querySelectorAll(trigger.getAttribute("z-toggle")).forEach(target => {
          trigger.addEventListener("change", () => target.hidden = !target.hidden);
        });
      }

      // The colour swatch is read-only and just mirrors the event's calendar;
      // track the selection so it updates as soon as the calendar is changed.
      const select = form.querySelector("select[name=calendar_id]");
      const swatch = form.querySelector("input[type=color]");

      if(select && swatch) {
        select.addEventListener("change", () => {
          const color = select.selectedOptions[0]?.dataset.color;
          if(color) swatch.value = color;
        });
      }
    })();
  </script>
</form>
