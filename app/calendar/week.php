<?php
// The calendar week view.

const TASK_DEADLINE_COLOR = "#e06c75";

$timezone = getenv("TIMEZONE") ?: "Europe/Amsterdam";

$from = (new DateTime($_GET['date'], new DateTimeZone($timezone)))->modify('monday this week')->setTime(0, 0, 0);
$to = (clone $from)->modify('+7 days');

$now = new DateTime('now', new DateTimeZone($timezone));
$now_day_number = ($now >= $from && $now < $to) ? (int) $now->format('N') : null;
$now_top = ((int) $now->format('H') * 60 + (int) $now->format('i')) / 1440 * 100;

$appointments = \store\list_appointments(
  cast_datetime_utc($from->format('Y-m-d'), $from->format('H:i:s'), $timezone),
  cast_datetime_utc($to->format('Y-m-d'), $to->format('H:i:s'), $timezone)
);

foreach($appointments as &$appointment) {
  $appointment['starts_at'] = cast_datetime_local($appointment['starts_at'], $timezone);
  $appointment['ends_at'] = cast_datetime_local($appointment['ends_at'], $timezone);
}

unset($appointment); // gotta love in place mutation lol

// Recurring appointments aren't stored per occurrence; expand them across the
// visible week so each firing lands in the grid. Recurrences are expressed in
// local wall time (that's how the sync derives them), so the master is
// localised first and enumeration happens in the same space. Occurrences join
// the plain appointment pool below, inheriting its filtering and layout.
$window_from = new DateTimeImmutable($from->format("Y-m-d\TH:i:s"));
$window_to = new DateTimeImmutable($to->format("Y-m-d\TH:i:s"));

foreach(\store\list_recurring_appointments(
  cast_datetime_utc($from->format('Y-m-d'), $from->format('H:i:s'), $timezone),
  cast_datetime_utc($to->format('Y-m-d'), $to->format('H:i:s'), $timezone)
) ?: [] as $series) {
  $base = new DateTimeImmutable(cast_datetime_local($series['starts_at'], $timezone));
  $duration = (new DateTimeImmutable(cast_datetime_local($series['ends_at'], $timezone)))->getTimestamp() - $base->getTimestamp();

  $until = $series['recurrence_until']
    ? new DateTimeImmutable(cast_datetime_local($series['recurrence_until'], $timezone)) : null;
  $count = $series['recurrence_count'] !== null ? (int) $series['recurrence_count'] : null;

  // Widen the lower bound by the duration so occurrences starting just before
  // the week but spilling into it aren't dropped; the upper bound stays
  // exclusive so an occurrence at next Monday 00:00 belongs to the next week.
  $occurrences = \recurrence\occurrences(
    $series['recurrence'], $base,
    $window_from->modify("-$duration seconds"),
    $window_to->modify('-1 second'),
    $until, $count
  );

  foreach($occurrences as $occ) {
    $occurrence = $series;
    $occurrence['starts_at'] = $occ->format("Y-m-d H:i:s");
    $occurrence['ends_at'] = $occ->modify("+$duration seconds")->format("Y-m-d H:i:s");
    $appointments[] = $occurrence;
  }
}

$hidden = array_filter(explode(",", $_GET['hidden'] ?? ""));
$hide_not_going = ($_GET['not_going'] ?? "") === "1";
$hide_travel = ($_GET['hide_travel'] ?? "") === "1";
$hide_tasks = ($_GET['no_tasks'] ?? "") === "1";
$hide_timings = ($_GET['no_timings'] ?? "") === "1";

$appointments = array_filter($appointments, function($a) use ($hidden, $hide_not_going) {
  if($hide_not_going && !$a['going']) return false;
  return !in_array($a['calendar_id'] ?? $a['subscription_id'], $hidden, true);
});

$all_day_appointments = array_values(array_filter($appointments, fn($a) => $a['all_day']));
$timed_appointments = array_values(array_filter($appointments, fn($a) => !$a['all_day']));

// Credits to @m1kadev for implementing this in Python originally, for
// a project that shall not be named on legal grounds. Happily stolen:)

$days = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];

// Week bounds in the same (timezone-naive) wall-clock space as the localised
// appointment times, so they can be compared and clamped against each other.
$week_start = new DateTime($from->format("Y-m-d\TH:i:s"));
$week_stop = new DateTime($to->format("Y-m-d\TH:i:s"));

// Task deadlines ride along as one-hour appointments ending at the due date,
// so the week view shows when things are actually due. Due dates are stored in
// local wall time already, matching the localised appointment times above.
if(!$hide_tasks) {
  // Horizon is ignored here: a deadline should stay visible on the week it
  // falls in, even if it's further out than the todo listing would surface.
  foreach(\store\list_tasks("not:done not:nvm", [], respect_horizon: false) ?: [] as $task) {
    if(!$task['next']) continue;

    $due = new DateTime($task['next']);
    if($due <= $week_start || $due > $week_stop) continue;

    $timed_appointments[] = [
      'id' => $task['id'],
      'title' => esc_inner($task['title']),
      'starts_at' => (clone $due)->modify('-1 hour')->format("Y-m-d H:i:s"),
      'ends_at' => $due->format("Y-m-d H:i:s"),
      'all_day' => false,
      'going' => true,
      'calendar_id' => null,
      'subscription_id' => null,
      'calendar_color' => TASK_DEADLINE_COLOR,
      'location' => null,
      'recurrence' => null,
      'meeting' => false,
      'travel_before' => 0,
      'travel_after' => 0,
      'is_task' => true,
    ];
  }
}

// Tracked timings render as thin coloured lines down the left gutter of each
// day rather than as appointments, so they annotate the day without competing
// with real events for column space. Coloured by their first tag's root (grey
// when untagged), split across midnight like travel bands.
$timings = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];

if(!$hide_timings) {
  $tags_by_id = [];
  foreach(\store\list_tags() ?: [] as $tag) $tags_by_id[$tag['id']] = $tag;

  // Climb parent_id to the root tag and hand back its colour.
  $root_color = function($id) use ($tags_by_id) {
    $tag = $tags_by_id[$id] ?? null;
    while($tag && $tag['parent_id']) $tag = $tags_by_id[$tag['parent_id']] ?? null;
    return @$tag['color'];
  };

  foreach(\store\list_timings_between(
    cast_datetime_utc($from->format('Y-m-d'), $from->format('H:i:s'), $timezone),
    cast_datetime_utc($to->format('Y-m-d'), $to->format('H:i:s'), $timezone)
  ) ?: [] as $timing) {
    $start = max(new DateTime(cast_datetime_local($timing['starts_at'], $timezone)), $week_start);
    $end = min(new DateTime(cast_datetime_local($timing['ends_at'], $timezone)), $week_stop);

    $color = $root_color($timing['first_tag_id']) ?: '#cccccc';
    $title = $timing['description'] ?: "No description";

    $day_cursor = (clone $start)->setTime(0, 0, 0);

    while($day_cursor < $end) {
      $day_end = (clone $day_cursor)->modify('+1 day');
      $day_number = (int) $day_cursor->format('N');

      if(isset($timings[$day_number])) {
        $segment_start = max($start, $day_cursor);
        $segment_end = min($end, $day_end);

        $timings[$day_number][] = [
          'top' => ($segment_start->getTimestamp() - $day_cursor->getTimestamp()) / 60 / 1440 * 100,
          'height' => ($segment_end->getTimestamp() - $segment_start->getTimestamp()) / 60 / 1440 * 100,
          'color' => $color,
          'title' => $title,
        ];
      }

      $day_cursor = $day_end;
    }
  }
}

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

      // Drag-resizable when calendar-owned (not a subscription feed or task
      // deadline), non-recurring, and wholly within this one day so a dragged
      // edge maps cleanly onto a single start/end. Everything else is
      // edited through the full editor.
      $segment['editable'] = empty($appointment['subscription_id'])
        && empty($appointment['is_task'])
        && empty($appointment['recurrence'])
        && $segment_start == $start_dt
        && $segment_end == $end_dt;

      $days[$day_number][] = $segment;
    }

    $day_cursor = $day_end;
  }
}

// Travel time renders as a gray band before/after an appointment. Bands are
// collected per day as minutes-from-midnight intervals, then merged so the
// travel windows of adjacent appointments never stack into a darker patch.
// They live outside the column layout below, so they can't affect it.
$travel = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];

foreach($hide_travel ? [] : $timed_appointments as $appointment) {
  if(!$appointment['going']) continue;

  $bands = [];

  if($before = (int) $appointment['travel_before']) {
    $start = new DateTime($appointment['starts_at']);
    $bands[] = [(clone $start)->modify("-$before minutes"), $start];
  }

  if($after = (int) $appointment['travel_after']) {
    $end = new DateTime($appointment['ends_at']);
    $bands[] = [$end, (clone $end)->modify("+$after minutes")];
  }

  foreach($bands as [$band_start, $band_end]) {
    $band_start = max($band_start, $week_start);
    $band_end = min($band_end, $week_stop);

    $day_cursor = (clone $band_start)->setTime(0, 0, 0);

    while($day_cursor < $band_end) {
      $day_end = (clone $day_cursor)->modify('+1 day');
      $day_number = (int) $day_cursor->format('N');

      $segment_start = max($band_start, $day_cursor);
      $segment_end = min($band_end, $day_end);

      $travel[$day_number][] = [
        ($segment_start->getTimestamp() - $day_cursor->getTimestamp()) / 60,
        ($segment_end->getTimestamp() - $day_cursor->getTimestamp()) / 60,
      ];

      $day_cursor = $day_end;
    }
  }
}

// Merge overlapping/touching intervals per day so no pixel is painted twice.
foreach($travel as $day_number => $intervals) {
  usort($intervals, fn($a, $b) => $a[0] <=> $b[0]);

  $merged = [];
  foreach($intervals as [$start, $end]) {
    $last = array_key_last($merged);
    if($merged !== [] && $start <= $merged[$last][1]) {
      $merged[$last][1] = max($merged[$last][1], $end);
    } else {
      $merged[] = [$start, $end];
    }
  }

  $travel[$day_number] = $merged;
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
    <button type="button" title="Previous week" data-date="<?= (clone $from)->modify('-7 days')->format('Y-m-d') ?>">&larr;</button>
    <button type="button" data-today data-date="<?= (new DateTime('today', new DateTimeZone($timezone)))->format('Y-m-d') ?>">Today</button>
    <button type="button" title="Next week" data-date="<?= (clone $from)->modify('+7 days')->format('Y-m-d') ?>">&rarr;</button>
    <button type="button" title="Sync calendars" data-sync><i class="fa-solid fa-rotate"></i></button>
    <button type="button" class="calendar-week__calendars" data-sidebar-toggle><i class="fa-regular fa-sidebar-flip"></i></button>
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
                data-id="<?= esc_attr($appointment['id']) ?>"
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
      <section class="day" data-date="<?= (clone $from)->modify('+' . ($day_number - 1) . ' days')->format('Y-m-d') ?>">
        <?php if($day_number === $now_day_number): ?>
          <div class="calendar-week__now" style="--now-top: <?= $now_top ?>"></div>
        <?php endif; ?>

        <?php foreach($travel[$day_number] as [$travel_start, $travel_end]): ?>
          <div class="day__travel" style="--travel-top: <?= $travel_start / 1440 * 100 ?>; --travel-height: <?= ($travel_end - $travel_start) / 1440 * 100 ?>"></div>
        <?php endforeach; ?>

        <?php foreach($timings[$day_number] as $timing): ?>
          <div class="day__timing" title="<?= esc_attr($timing['title']) ?>"
            style="--timing-top: <?= $timing['top'] ?>; --timing-height: <?= $timing['height'] ?>; --timing-color: <?= esc_attr($timing['color']) ?>">
            <span class="day__timing-dot day__timing-dot--start"><i class="fa-solid fa-clock"></i></span>
            <span class="day__timing-dot day__timing-dot--end"></span>
          </div>
        <?php endforeach; ?>

        <?php foreach($day as $appointment): ?>
          <?php $layout = $appointment['layout']; ?>
          <article class="appointment<?= $appointment['going'] ? "" : " appointment--not-going" ?><?= empty($appointment['is_task']) ? "" : " appointment--task" ?>"
                    <?= empty($appointment['is_task']) ? 'data-id="' . esc_attr($appointment['id']) . '"' : '' ?>
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

            <?php if($appointment['recurrence'] || $appointment['meeting'] || !empty($appointment['is_task'])): ?>
              <span class="appointment__icons">
                <?php if(!empty($appointment['is_task'])): ?><i class="fa-solid fa-flag"></i><?php endif; ?>
                <?php if($appointment['recurrence']): ?><i class="fa-solid fa-repeat" title="<?= esc_attr(describe_recurrence($appointment['recurrence'])) ?>"></i><?php endif; ?>
                <?php if($appointment['meeting']): ?><i class="fa-solid fa-video"></i><?php endif; ?>
              </span>
            <?php endif; ?>

            <?php if(!empty($appointment['editable'])): ?>
              <span class="appointment__handle appointment__handle--top"></span>
              <span class="appointment__handle appointment__handle--bottom"></span>
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
    const now = root.querySelector(".calendar-week__now");

    if(root.dataset.scrollTop) {
      // Retain scroll position from previous view, if set.
      container.scrollTop = parseFloat(root.dataset.scrollTop);
    }
    else if(now) {
      // Set now-line to 1/4th of the screen, if today is visible.
      const nowTop = day.offsetHeight * <?= $now_top ?> / 100;
      container.scrollTop = nowTop - container.clientHeight / 4;
    }
    else if(day) {
      // Set beginning of day to 6:30, if all else fails.
      container.scrollTop = day.offsetHeight / 24 * 6.5;
    }

    container.addEventListener("scroll", () => root.dataset.scrollTop = container.scrollTop);
  })();
</script>
