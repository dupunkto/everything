<?php
// The calendar week view.

// date is any day within the week
// filter? is whether or not filters are applied
// visible[] is the calendars to show
// travel? is whether to show travel time
// tasks? is whether to show task deadlines
// timings? is whether to show timings
// declined? is whether to show events marked as 'not going'

$tz = new DateTimeZone(getenv("TIMEZONE") ?: "Europe/Amsterdam");
$today = new DateTime('today', $tz);
$now = new DateTime('now', $tz);

// The window as timezone-naive wall time; all layout math lives there.
$from = new DateTime((new DateTime(@$_GET['date'] ?: 'today', $tz))->modify('monday this week')->format('Y-m-d'));
$to = (clone $from)->modify('+7 days');

$filtered = isset($_GET['filter']);
$visible = $filtered ? (array) (@$_GET['visible'] ?: []) : null;
$show = fn($extra) => !$filtered || isset($_GET[$extra]);

$appointments = array_filter(\calendar\appointments($from, $to), function($a) use ($visible, $show) {
  if($visible !== null && !in_array($a['calendar_id'] ?? $a['subscription_id'], $visible)) return false;
  return $show('declined') || $a['going'];
});

$timed = array_filter($appointments, fn($a) => !$a['all_day']);
if($show('tasks')) $timed = array_merge($timed, \calendar\task_deadlines($from, $to));

$all_day = \calendar\all_day_lanes(array_filter($appointments, fn($a) => $a['all_day']), $from);
$days = array_map('\calendar\layout', \calendar\day_segments($timed, $from, $to));
$travel = $show('travel') ? \calendar\travel_bands($timed, $from, $to) : [];
$timings = $show('timings') ? \calendar\timing_lines($from, $to) : [];

$now_date = $now->format('Y-m-d');
$now_top = ((int) $now->format('H') * 60 + (int) $now->format('i')) / 1440 * 100;

$color = fn($a) => esc_attr($a['calendar_color'] ?? $a['subscription_color'] ?? '#cccccc');
?>
<div class="calendar-week__header">
  <div class="calendar-week__lead">
    <div class="calendar-week__tools">
      <button type="button" title="Filters" data-sidebar><i class="fa-regular fa-sidebar-flip"></i></button>
    </div>
    <h1 class="calendar-week__title"><strong><?= $from->format('F') ?></strong> <?= $from->format('Y') ?></h1>
  </div>

  <input type="hidden" name="date" value="<?= $from->format('Y-m-d') ?>" form="calendar-filters">

  <div class="calendar-week__nav">
    <button type="button" title="Sync calendars" data-sync x-get="/calendar/sync"
      x-target="#calendar-view" x-data="#calendar-filters"><i class="fa-solid fa-rotate"></i></button>
    <button type="button" title="Previous week" x-get="/calendar/week?date=<?= (clone $from)->modify('-7 days')->format('Y-m-d') ?>"
      x-target="#calendar-view" x-data="#calendar-filters">&larr;</button>
    <button type="button" data-today x-get="/calendar/week?date=<?= $today->format('Y-m-d') ?>"
      x-target="#calendar-view" x-data="#calendar-filters">Today</button>
    <button type="button" title="Next week" x-get="/calendar/week?date=<?= (clone $from)->modify('+7 days')->format('Y-m-d') ?>"
      x-target="#calendar-view" x-data="#calendar-filters">&rarr;</button>
  </div>
</div>
<div class="calendar-week">
  <div class="calendar-week__labels">
    <?php foreach(array_keys($days) as $date): $day = new DateTime($date) ?>
      <h2 class="calendar-week__label">
        <?= $day->format('l') ?>
        <span class="calendar-week__label-day<?= $date == $now_date ? ' calendar-week__label-day--today' : '' ?>">
          <?= $day->format('j') ?>
        </span>
      </h2>
    <?php endforeach ?>
  </div>

  <?php if($all_day): ?>
    <div class="calendar-week__all-day">
      <?php foreach($all_day as $appointment): ?>
        <article class="appointment appointment--all-day<?= $appointment['going'] ? "" : " appointment--not-going" ?>"
                 data-id="<?= esc_attr($appointment['id']) ?>"
                 style="--appointment-column: <?= $appointment['layout']['column'] ?>; --appointment-span: <?= $appointment['layout']['span'] ?>;
                        --appointment-row: <?= $appointment['layout']['row'] ?>;
                        --appointment-color: <?= $color($appointment) ?>">
          <h3 class="appointment__title"><?= $appointment['title'] ?></h3>

          <?php if($appointment['recurrence'] || $appointment['meeting']): ?>
            <span class="appointment__icons">
              <?php if($appointment['recurrence']): ?><i class="fa-solid fa-repeat" title="<?= esc_attr(describe_recurrence($appointment['recurrence'])) ?>"></i><?php endif ?>
              <?php if($appointment['meeting']): ?><i class="fa-solid fa-video"></i><?php endif ?>
            </span>
          <?php endif ?>
        </article>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <div class="calendar-week__days">
    <div class="calendar-week__hours">
      <?php for($hour = 0; $hour < 24; $hour++): ?>
        <span class="calendar-week__hour" style="--hour-index: <?= $hour ?>"><?= sprintf('%02d:00', $hour) ?></span>
      <?php endfor ?>
    </div>

    <?php foreach($days as $date => $day): ?>
      <section class="day" data-date="<?= $date ?>">
        <?php if($date == $now_date): ?>
          <div class="calendar-week__now" style="--now-top: <?= $now_top ?>"></div>
        <?php endif ?>

        <?php foreach($travel[$date] ?? [] as $band): ?>
          <div class="day__travel" style="--travel-top: <?= $band['top'] ?>; --travel-height: <?= $band['height'] ?>"></div>
        <?php endforeach ?>

        <?php foreach($timings[$date] ?? [] as $timing): ?>
          <div class="day__timing" title="<?= esc_attr($timing['title']) ?>"
               style="--timing-top: <?= $timing['top'] ?>; --timing-height: <?= $timing['height'] ?>; --timing-color: <?= esc_attr($timing['color']) ?>">
            <span class="day__timing-dot day__timing-dot--start"><i class="fa-solid fa-clock"></i></span>
            <span class="day__timing-dot day__timing-dot--end"></span>
          </div>
        <?php endforeach ?>

        <?php foreach($day as $appointment): $layout = $appointment['layout'] ?>
          <article class="appointment<?= $appointment['going'] ? "" : " appointment--not-going" ?><?= empty($appointment['is_task']) ? "" : " appointment--task" ?>"
                   <?= empty($appointment['is_task']) ? 'data-id="' . esc_attr($appointment['id']) . '"' : '' ?>
                   style="--appointment-top: <?= $layout['top'] ?>;
                          --appointment-height: <?= $layout['height'] ?>;
                          <?= $appointment['going']
                            ? "--appointment-width: {$layout['width']}; --appointment-left: {$layout['left']};"
                            : "--appointment-inset: {$layout['inset']};" ?>
                          --appointment-color: <?= $color($appointment) ?>">
            <h3 class="appointment__title"><?= $appointment['title'] ?></h3>

            <?php if($appointment['location']): ?>
              <span class="appointment__location"><?= $appointment['location'] ?></span>
            <?php endif ?>

            <span class="appointment__duration">
              <time class="appointment__start" datetime="<?= $appointment['starts_at'] ?>"><?= date("H:i", strtotime($appointment['starts_at'])) ?></time> – <time class="appointment__end" datetime="<?= $appointment['ends_at'] ?>"><?= date("H:i", strtotime($appointment['ends_at'])) ?></time>
            </span>

            <?php if($appointment['recurrence'] || $appointment['meeting'] || !empty($appointment['is_task'])): ?>
              <span class="appointment__icons">
                <?php if(!empty($appointment['is_task'])): ?><i class="fa-solid fa-flag"></i><?php endif ?>
                <?php if($appointment['recurrence']): ?><i class="fa-solid fa-repeat" title="<?= esc_attr(describe_recurrence($appointment['recurrence'])) ?>"></i><?php endif ?>
                <?php if($appointment['meeting']): ?><i class="fa-solid fa-video"></i><?php endif ?>
              </span>
            <?php endif ?>

            <?php if(!empty($appointment['editable'])): ?>
              <span class="appointment__handle appointment__handle--top"></span>
              <span class="appointment__handle appointment__handle--bottom"></span>
            <?php endif ?>
          </article>
        <?php endforeach ?>
      </section>
    <?php endforeach ?>
  </div>
</div>
