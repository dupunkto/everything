<?php
  // Appointment edit popup.

  $appointment = \store\get_appointment(@$_POST['id'] ?? @$_GET['id'])
    or fail("Appointment not found.", status: 404);

  $is_subscription = !empty($appointment['subscription_id']);
  $source_color = $appointment['calendar_color'] ?? $appointment['subscription_color'];

  if(isset($_POST['id'])) {
    $going = !empty($_POST['going']);
    $urgent = !empty($_POST['urgent']);
    $travel = !empty($_POST['travel']);

    $travel_before = $travel ? max(0, (int) $_POST['travel_before']) : 0;
    $travel_after = $travel ? max(0, (int) $_POST['travel_after']) : 0;

    if($is_subscription) {
      \store\update_appointment_meta(
        $appointment['id'], $going, $urgent, $travel_before, $travel_after
      ) or fail("Could not update appointment.");
    }
    else {
      $recurrence = !empty($_POST['repeating'])
        && !empty($_POST['recurrence']) ? $_POST['recurrence'] : null;

      \store\update_appointment(
        $appointment['id'],
        $_POST['title'],
        $_POST['content'],
        cast_datetime_utc($_POST['start_date'], $_POST['start_time']),
        cast_datetime_utc($_POST['end_date'], $_POST['end_time']),
        $_POST['location'],
        $_POST['meeting'],
        $recurrence,
        !empty($_POST['all_day']),
        $going,
        $urgent,
        $travel_before,
        $travel_after,
        @$_POST['calendar_id'] ?: null
      ) or fail("Could not update appointment.");
    }

    // The caller refreshes the week itself; nothing to render back.
    http_response_code(204); exit;
  }

  $starts = new DateTime(cast_datetime_local($appointment['starts_at']));
  $ends = new DateTime(cast_datetime_local($appointment['ends_at']));

  $is_repeating = !empty($appointment['recurrence']);
  $has_travel = (int) $appointment['travel_before'] || (int) $appointment['travel_after'];

  $calendars = $is_subscription ? [] : (\store\list_calendars() ?: []);
?>
<form id="calendar-edit" x-post="/calendar/edit" x-on="change" x-refresh="#calendar-view">
  <input type="hidden" name="id" value="<?= esc_attr($appointment['id']) ?>">

  <div class="calendar-editor__head title-check" z-circle>
    <input type="text" name="title" placeholder="Title" value="<?= esc_attr($appointment['title']) ?>"
      <?= $is_subscription ? 'readonly' : 'required autofocus' ?>>
    <?php circle() ?>
    <label class="title-check__urgent" title="Circle">
      <input type="checkbox" name="urgent" aria-label="Circle" <?= cast_boolean($appointment['urgent']) ? 'checked' : '' ?>>
      <i class="fa-regular fa-flag"></i>
      <i class="fa-solid fa-flag"></i>
    </label>
  </div>

  <?php if(!$is_subscription): ?>
    <?php if(count($calendars) > 1): ?>
      <div class="calendar-editor__source">
        <select name="calendar_id" required>
          <?php foreach($calendars as $c): ?>
            <option value="<?= esc_attr($c['id']) ?>" data-color="<?= esc_attr($c['color']) ?>" <?= $c['id'] == $appointment['calendar_id'] ? 'selected' : '' ?>>
              <?= esc_inner($c['title']) ?><?php if($c['subtitle']) echo " (" . esc_inner($c['subtitle']) . ")" ?>
            </option>
          <?php endforeach ?>
        </select>
        <input type="color" value="<?= esc_attr($source_color) ?>" tabindex="-1" readonly>
      </div>
    <?php endif ?>

    <textarea name="content" placeholder="Description"><?= esc_inner($appointment['content'] ?? '') ?></textarea>
    <input name="location" type="text" placeholder="Location" value="<?= esc_attr($appointment['location'] ?? '') ?>">
    <input name="meeting" type="text" placeholder="Meeting URL" value="<?= esc_attr($appointment['meeting'] ?? '') ?>">

    <label class="field">
      Starts
      <span class="datetime-pair">
        <input name="start_date" type="date" value="<?= $starts->format('Y-m-d') ?>" required>
        <input name="start_time" type="time" value="<?= $starts->format('H:i') ?>" required>
      </span>
    </label>
    <label class="field">
      Ends
      <span class="datetime-pair">
        <input name="end_date" type="date" value="<?= $ends->format('Y-m-d') ?>" required>
        <input name="end_time" type="time" value="<?= $ends->format('H:i') ?>" required>
      </span>
    </label>

    <label class="check"><input type="checkbox" name="all_day" <?= cast_boolean($appointment['all_day']) ? 'checked' : '' ?>> All day</label>

    <label class="check">
      <input type="checkbox" name="repeating" z-toggle="#calendar-edit-recurrence" <?= $is_repeating ? 'checked' : '' ?>>
      Repeating
    </label>

    <div id="calendar-edit-recurrence" <?= $is_repeating ? '' : 'hidden' ?>>
      <input name="recurrence" type="text" placeholder="cron or number of days" value="<?= esc_attr($appointment['recurrence'] ?? '') ?>">
    </div>
  <?php endif ?>

  <label class="check"><input type="checkbox" name="going" <?= cast_boolean($appointment['going']) ? 'checked' : '' ?>> Going</label>

  <label class="check">
    <input type="checkbox" name="travel" z-toggle="#calendar-edit-travel" <?= $has_travel ? 'checked' : '' ?>>
    Travel time
  </label>

  <div id="calendar-edit-travel" <?= $has_travel ? '' : 'hidden' ?>>
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
    <button type="button" data-id="<?= esc_attr($appointment['id']) ?>"
      x-post="/calendar/delete" x-refresh="#calendar-view">Delete</button>
  <?php endif ?>

  <script>
    (() => {
      // The read-only swatch mirrors the owning calendar's colour; keep it
      // live with the selection.
      const form = document.currentScript.closest("form");
      const select = form.querySelector("select[name=calendar_id]");
      const swatch = form.querySelector("input[type=color]");

      select?.addEventListener("change", () => {
        swatch.value = select.selectedOptions[0]?.dataset.color || swatch.value;
      });
    })();
  </script>
</form>
