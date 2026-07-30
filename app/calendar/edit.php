<?php
  // Appointment edit popup.

  $appointment = \store\get_appointment(@$_POST['id'] ?? @$_GET['id'])
    or fail("Appointment not found.", status: 404);

  $is_subscription = !empty($appointment['subscription_id']);

  if(isset($_POST['id'])) {
    $going = cast_boolean(@$_POST['going']);
    $urgent = cast_boolean(@$_POST['urgent']);
    $travel = cast_boolean(@$_POST['travel']);

    $travel_before = $travel ? max(0, (int) $_POST['travel_before']) : 0;
    $travel_after = $travel ? max(0, (int) $_POST['travel_after']) : 0;

    if($is_subscription) {
      $fields = \core\diff($appointment,
        going: $going,
        urgent: $urgent,
        travel_before: $travel_before,
        travel_after: $travel_after);

      \store\update_appointment_meta(
        $appointment['id'], $going, $urgent, $travel_before, $travel_after
      );
    }
    else {
      $recurrence = isset($_POST['repeating']) && isset($_POST['recurrence']) ?
        $_POST['recurrence'] : null;

      $starts_at = cast_datetime_utc($_POST['start_date'], $_POST['start_time']);
      $ends_at = cast_datetime_utc($_POST['end_date'], $_POST['end_time']);

      $recurrence_start = (new \DateTimeImmutable($starts_at))->setTimezone(new \DateTimeZone(TIMEZONE));
      if($recurrence && !\recurrence\valid($recurrence, $recurrence_start))
        fail("Invalid recurrence rule.", status: 400);

      $fields = \core\diff($appointment,
        title: $_POST['title'],
        content: $_POST['content'],
        starts_at: $starts_at,
        ends_at: $ends_at,
        location: $_POST['location'],
        meeting: $_POST['meeting'],
        recurrence: $recurrence,
        all_day: cast_boolean(@$_POST['all_day']),
        going: $going,
        urgent: $urgent,
        travel_before: $travel_before,
        travel_after: $travel_after,
        calendar_id: @$_POST['calendar_id'] ?: null);

      \store\update_appointment(
        $appointment['id'],
        $_POST['title'],
        $_POST['content'],
        $starts_at,
        $ends_at,
        $_POST['location'],
        $_POST['meeting'],
        $recurrence,
        cast_boolean(@$_POST['all_day']),
        $going,
        $urgent,
        $travel_before,
        $travel_after,
        @$_POST['calendar_id'] ?: null
      );
    }

    \store\put_audit_log('appointments', $appointment['id'],
      "Updated [" . join(", ", $fields) . "] for appointments/{$appointment['id']}.", 'user');

    \caldav\mark_resource_changed('appointment', $appointment['id']);

    // The caller refreshes the week itself; nothing to render back.
    http_response_code(204); exit;
  }

  $starts = new DateTime(cast_datetime_local($appointment['starts_at']));
  $ends = new DateTime(cast_datetime_local($appointment['ends_at']));

  $is_repeating = !empty($appointment['recurrence']);
  $has_travel = (int) $appointment['travel_before'] || (int) $appointment['travel_after'];

  $calendars = $is_subscription ? [] : \store\list_calendars();
?>
<form id="calendar-edit" x-post="/calendar/edit" x-on="change" x-refresh="#calendar-view">
  <input type="hidden" name="id" value="<?= esc_attr($appointment['id']) ?>">

  <div class="calendar-editor__head title-check" z-circle>
    <textarea name="title" placeholder="Title" rows="1"
      <?= $is_subscription ? 'readonly' : 'required autofocus' ?>><?= esc_inner($appointment['title']) ?></textarea>
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
              <?= esc_inner($c['title']) ?><?php if($c['subtitle']) echo " (" . esc_inner(mb_strtolower($c['subtitle'])) . ")" ?>
            </option>
          <?php endforeach ?>
        </select>
        <input type="color" value="<?= esc_attr($appointment['calendar_color'] ?? $appointment['subscription_color']) ?>" tabindex="-1" readonly>
      </div>
    <?php endif ?>

    <textarea name="content" placeholder="Description"><?= esc_inner($appointment['content'] ?? '') ?></textarea>
  <?php endif ?>

  <?php if(!$is_subscription || !empty($appointment['location'])): ?>
    <textarea name="location" placeholder="Location" rows="1" <?= $is_subscription ? 'readonly' : '' ?>><?= esc_inner($appointment['location'] ?? '') ?></textarea>
  <?php endif ?>

  <?php if(!$is_subscription || !empty($appointment['meeting'])): ?>
    <input name="meeting" type="text" placeholder="Meeting URL" value="<?= esc_attr($appointment['meeting'] ?? '') ?>" <?= $is_subscription ? 'readonly' : '' ?>>
  <?php endif ?>

  <?php if(!$is_subscription): ?>
    <label class="field">
      Starts
      <span class="datetime-pair">
        <input name="start_date" type="date" value="<?= $starts->format('Y-m-d') ?>" required>
        <input name="start_time" type="time" lang="<?= TIME_LANG ?>" value="<?= $starts->format('H:i') ?>" required>
      </span>
    </label>
    <label class="field">
      Ends
      <span class="datetime-pair">
        <input name="end_date" type="date" value="<?= $ends->format('Y-m-d') ?>" required>
        <input name="end_time" type="time" lang="<?= TIME_LANG ?>" value="<?= $ends->format('H:i') ?>" required>
      </span>
    </label>

    <label class="check"><input type="checkbox" name="all_day" <?= cast_boolean($appointment['all_day']) ? 'checked' : '' ?>> All day</label>

    <label class="check">
      <input type="checkbox" name="repeating" z-toggle="#calendar-edit-recurrence" <?= $is_repeating ? 'checked' : '' ?>>
      Repeating
    </label>

    <div id="calendar-edit-recurrence" <?= $is_repeating ? '' : 'hidden' ?>>
      <input name="recurrence" type="text" placeholder="FREQ=WEEKLY;BYDAY=MO,WE,FR" value="<?= esc_attr($appointment['recurrence'] ?? '') ?>">
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
      x-post="/calendar/delete" z-key="d" x-refresh="#calendar-view">Delete</button>
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
