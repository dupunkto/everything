<?php
// The calendar week view.

$timezone = getenv("TIMEZONE") ?: "Europe/Amsterdam";

$from = (new DateTime($_GET['date'], new DateTimeZone($timezone)))->modify('monday this week')->setTime(0, 0, 0);
$to = (clone $from)->modify('+7 days'); // exclusive upper bound for the query (next Monday)

$now = new DateTime('now', new DateTimeZone($timezone));
$now_day_number = ($now >= $from && $now < $to) ? (int) $now->format('N') : null;
$now_top = ((int) $now->format('H') * 60 + (int) $now->format('i')) / 1440 * 100;

// Appointments are stored in UTC (see cast_datetime_utc); the query bounds
// need to be in UTC too, and the fetched rows need converting back to local
// wall-clock time before any of the day-layout math below runs on them.
$appointments = \store\list_appointments(
  cast_datetime_utc($from->format('Y-m-d'), $from->format('H:i:s'), $timezone),
  cast_datetime_utc($to->format('Y-m-d'), $to->format('H:i:s'), $timezone)
);

foreach($appointments as &$appointment) {
  $appointment['starts_at'] = cast_datetime_local($appointment['starts_at'], $timezone);
  $appointment['ends_at'] = cast_datetime_local($appointment['ends_at'], $timezone);
}
unset($appointment);

$all_day_appointments = array_values(array_filter($appointments, fn($a) => $a['all_day']));
$timed_appointments = array_values(array_filter($appointments, fn($a) => !$a['all_day']));

// Credits to @m1kadev for implementing this in Python originally, for
// a project that shall not be named on legal grounds. Happily stolen:)

$days = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];

// Week bounds in the same (timezone-naive) wall-clock space as the localised
// appointment times, so they can be compared and clamped against each other.
$week_start = new DateTime($from->format("Y-m-d\TH:i:s"));
$week_stop = new DateTime($to->format("Y-m-d\TH:i:s"));

foreach($timed_appointments as $appointment) {
  $start_dt = new DateTime($appointment['starts_at']);
  $end_dt = min(new DateTime($appointment['ends_at']), $week_stop);

  // Appointments crossing midnight are split into one segment per day, each
  // clipped to that day's boundaries, so every day only lays out the portion
  // of the appointment that actually falls within it. The cursor is clamped
  // to the displayed week; days of an appointment stretching into adjacent
  // weeks would otherwise wrap around into this week's columns.
  $day_cursor = max((clone $start_dt)->setTime(0, 0, 0), $week_start);

  while($day_cursor < $end_dt) {
    $day_end = (clone $day_cursor)->modify('+1 day');
    $day_number = (int) $day_cursor->format("N");

    if(isset($days[$day_number])) {
      $segment_start = max($start_dt, $day_cursor);
      $segment_end = min($end_dt, $day_end);

      // This enforces a minumum appointment length in the rendering.
      $layout_end = clone $segment_start;
      $layout_end->modify('+30 minutes');
      $layout_end = max($segment_end, $layout_end);

      $segment = $appointment;
      $segment['starts_at'] = $segment_start->format("Y-m-d H:i:s");
      $segment['ends_at'] = $segment_end->format("Y-m-d H:i:s");
      $segment['day_number'] = $day_number;
      $segment['layout_end'] = $layout_end->format("Y-m-d H:i:s");
      $days[$day_number][] = $segment;
    }

    $day_cursor = $day_end;
  }
}

foreach($days as $f => $day) {
  usort($day, fn($a, $b) =>
    strcmp($a['starts_at'], $b['starts_at'])
    ?: ($a['calendar_id'] <=> $b['calendar_id'])
    ?: ($a['subscription_id'] <=> $b['subscription_id'])
    ?: strcmp($a['id'], $b['id']));

  // Appointments marked 'not going' don't participate in the column layout;
  // they are drawn over whatever else is there, see below.
  $skipped = array_filter($day, fn($a) => !$a['going']);
  $day = array_filter($day, fn($a) => $a['going']);

  $columns = [];

  foreach($day as $appointment) {
    $found = false;

    foreach($columns as $h => $column) {
      $last_appointment = $column[array_key_last($column)];

      if($last_appointment['layout_end'] <= $appointment['starts_at']) {
        $found = true;
        $columns[$h][] = $appointment;
        break;
      }
    }

    if(!$found) {
      $columns[] = [$appointment];
    }
  }

  $total_columns = $columns ? count($columns) : 1;
  $days[$f] = [];

  foreach($columns as $i => $column) {
    foreach($column as $appointment) {
      $span = 1;

      for ($k = $i + 1; $k < $total_columns; $k++) {
        if (array_any($columns[$k], fn($other) =>
            $appointment['starts_at'] < $other['layout_end'] &&
            $appointment['layout_end'] > $other['starts_at']
        )) break;

        $span++;
      }

      $column_index = $i;
      $column_span = $span;

      $start_dt = new DateTimeImmutable($appointment['starts_at']);
      $end_dt = new DateTimeImmutable($appointment['ends_at']);

      $day_start = $start_dt->setTime(0, 0, 0);
      $day_end = $day_start->modify('+1 day');

      $total_minutes = ($day_end->getTimestamp() - $day_start->getTimestamp()) / 60;
      $start_minutes = ($start_dt->getTimestamp() - $day_start->getTimestamp()) / 60;
      $duration_minutes = ($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;

      $appointment['layout'] = [
        "top" => $start_minutes / $total_minutes * 100,
        "height" => $duration_minutes / $total_minutes * 100,
        "width" => $column_span / $total_columns * 100,
        "left" => $column_index / $total_columns * 100
      ];

      $days[$f][] = $appointment;
    }
  }

  // 'Not going' appointments overlay the laid-out ones at (almost) full
  // width. Each is inset a little further per appointment it overlaps, so
  // the left borders of everything underneath stay visible. Rendering
  // after the placed appointments puts them on top.
  foreach($skipped as $appointment) {
    $overlapping = fn($other) =>
      $appointment['starts_at'] < $other['layout_end'] &&
      $appointment['layout_end'] > $other['starts_at'];

    $level = count(array_filter($days[$f], $overlapping));

    $start_dt = new DateTimeImmutable($appointment['starts_at']);
    $end_dt = new DateTimeImmutable($appointment['ends_at']);

    $day_start = $start_dt->setTime(0, 0, 0);
    $day_end = $day_start->modify('+1 day');

    $total_minutes = ($day_end->getTimestamp() - $day_start->getTimestamp()) / 60;
    $start_minutes = ($start_dt->getTimestamp() - $day_start->getTimestamp()) / 60;
    $duration_minutes = ($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;

    $appointment['layout'] = [
      "top" => $start_minutes / $total_minutes * 100,
      "height" => $duration_minutes / $total_minutes * 100,
      "inset" => $level * 5
    ];

    $days[$f][] = $appointment;
  }
}

// The all-day bar uses the same greedy packing, but rotated: appointments
// occupy horizontal lanes, and each lands in the first lane that is still
// free on its starting day.
usort($all_day_appointments, fn($a, $b) =>
  strcmp($a['starts_at'], $b['starts_at'])
  ?: ($a['calendar_id'] <=> $b['calendar_id'])
  ?: ($a['subscription_id'] <=> $b['subscription_id'])
  ?: strcmp($a['id'], $b['id']));

$week_last_day = (clone $week_start)->modify('+6 days');

$lanes = []; // last occupied column per lane
$placed = [];

foreach($all_day_appointments as $appointment) {
  $start_dt = max($week_start, (new DateTime($appointment['starts_at']))->setTime(0, 0, 0));

  // An all-day appointment ending at midnight ends on the day before.
  $end_dt = new DateTime($appointment['ends_at']);
  $end_dt = $end_dt->format("H:i:s") === "00:00:00" ? $end_dt->modify('-1 day') : $end_dt;
  $end_dt = min($week_last_day, $end_dt->setTime(0, 0, 0));

  if($end_dt < $start_dt) continue;

  $column = $week_start->diff($start_dt)->days + 1;
  $span = $start_dt->diff($end_dt)->days + 1;

  $row = 1;
  while(($lanes[$row] ?? 0) >= $column) $row++;
  $lanes[$row] = $column + $span - 1;

  $appointment['layout'] = ["column" => $column, "span" => $span, "row" => $row];
  $placed[] = $appointment;
}

$all_day_appointments = $placed;

?>
<div class="calendar-week__header">
  <h1 class="calendar-week__title"><strong><?= $from->format('F') ?></strong> <?= $from->format('Y') ?></h1>

  <div class="calendar-week__nav">
    <button type="button" title="Previous week" x-get="/calendar/week?date=<?= (clone $from)->modify('-7 days')->format('Y-m-d') ?>" x-target="#calendar-week">&larr;</button>
    <button type="button" x-get="/calendar/week?date=<?= (new DateTime('today', new DateTimeZone($timezone)))->format('Y-m-d') ?>" x-target="#calendar-week">Today</button>
    <button type="button" title="Next week" x-get="/calendar/week?date=<?= (clone $from)->modify('+7 days')->format('Y-m-d') ?>" x-target="#calendar-week">&rarr;</button>
  </div>
</div>
<div class="calendar-week">
  <div class="calendar-week__labels">
    <?php for($day_number = 1; $day_number <= 7; $day_number++): ?>
      <h2 class="calendar-week__label">
        <?= (clone $from)->modify('+' . ($day_number - 1) . ' days')->format('l') ?>
        <span class="calendar-week__label-day<?= $day_number === $now_day_number ? ' calendar-week__label-day--today' : '' ?>">
          <?= (clone $from)->modify('+' . ($day_number - 1) . ' days')->format('j') ?>
        </span>
      </h2>
    <?php endfor; ?>
  </div>
  <?php if($all_day_appointments): ?>
  <div class="calendar-week__all-day">
    <?php foreach($all_day_appointments as $appointment): ?>
      <article class="appointment appointment--all-day<?= $appointment['going'] ? "" : " appointment--not-going" ?>"
                style="--appointment-column: <?= $appointment['layout']['column'] ?>; --appointment-span: <?= $appointment['layout']['span'] ?>;
                      --appointment-row: <?= $appointment['layout']['row'] ?>;
                      --appointment-color: <?= esc_attr($appointment['calendar_color'] ?? $appointment['subscription_color'] ?? '#cccccc') ?>">
        <h3 class="appointment__title">
          <?= $appointment['title'] ?>
        </h3>

        <?php if($appointment['recurrence'] || $appointment['meeting']): ?>
          <span class="appointment__icons">
            <?php if($appointment['recurrence']): ?><i class="fa-solid fa-repeat" title="<?= esc_attr(describe_recurrence($appointment['recurrence'])) ?>"></i><?php endif; ?>
            <?php if($appointment['meeting']): ?><i class="fa-solid fa-video"></i><?php endif; ?>
          </span>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="calendar-week__days">
    <div class="calendar-week__hours">
      <?php for($hour = 0; $hour < 24; $hour++): ?>
        <span class="calendar-week__hour" style="--hour-index: <?= $hour ?>">
          <?= sprintf('%02d:00', $hour) ?>
        </span>
      <?php endfor; ?>
    </div>
    <?php foreach($days as $day_number => $day): ?>
      <section class="day">
        <?php if($day_number === $now_day_number): ?>
          <div class="calendar-week__now" style="--now-top: <?= $now_top ?>"></div>
        <?php endif; ?>

        <?php foreach($day as $appointment): ?>
          <?php $layout = $appointment['layout']; ?>
          <article class="appointment<?= $appointment['going'] ? "" : " appointment--not-going" ?>"
                    style="--appointment-top: <?= $layout['top'] ?>;
                          --appointment-height: <?= $layout['height'] ?>;
                          <?= $appointment['going']
                            ? "--appointment-width: {$layout['width']}; --appointment-left: {$layout['left']};"
                            : "--appointment-inset: {$layout['inset']};" ?>
                          --appointment-color: <?= esc_attr($appointment['calendar_color'] ?? $appointment['subscription_color'] ?? '#cccccc') ?>">
            <h3 class="appointment__title">
              <?= $appointment['title'] ?>
            </h3>

            <?php if($appointment['location']): ?>
              <span class="appointment__location">
                <?= $appointment['location'] ?>
              </span>
            <?php endif; ?>

            <span class="appointment__duration">
              <time class="appointment__start" datetime="<?= $appointment['starts_at'] ?>"><?= date("H:i", strtotime($appointment['starts_at'])) ?></time> – <time class="appointment__end" datetime="<?= $appointment['ends_at'] ?>"><?= date("H:i", strtotime($appointment['ends_at'])) ?></time>
            </span>

            <?php if($appointment['recurrence'] || $appointment['meeting']): ?>
              <span class="appointment__icons">
                <?php if($appointment['recurrence']): ?><i class="fa-solid fa-repeat" title="<?= esc_attr(describe_recurrence($appointment['recurrence'])) ?>"></i><?php endif; ?>
                <?php if($appointment['meeting']): ?><i class="fa-solid fa-video"></i><?php endif; ?>
              </span>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<script>
  (() => {
    const root = document.currentScript.closest("#calendar-week");
    const container = root.querySelector(".calendar-week__days");
    const day = container.querySelector(".day");

    if(root.dataset.scrollTop !== undefined) {
      container.scrollTop = parseFloat(root.dataset.scrollTop);
    } else if(day) {
      container.scrollTop = day.offsetHeight / 24 * 6.5;
    }

    // Saved continuously (rather than on a single "unload" instant) since the
    // swap library gives no hook to run right before this content is
    // replaced, and reading scrollTop off an already-detached node is unreliable.
    container.addEventListener("scroll", () => root.dataset.scrollTop = container.scrollTop);
  })();
</script>
