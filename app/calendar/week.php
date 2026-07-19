<?php
// The calendar week view.

// date is any day within the week
// filter? is whether or not filters are applied
// visible[] is the calendars to show
// travel? is whether to show travel time
// tasks? is whether to show task deadlines
// birthdays? is whether to show contact birthdays
// timings? is whether to show timings
// declined? is whether to show events marked as 'not going'
// filtered? is whether to show events hidden by subscription filters
// habits? is whether to show habit badges

$tz = new DateTimeZone(TIMEZONE);
$today = new DateTime('today', $tz);
$now = new DateTime('now', $tz);

// The window as timezone-naive wall time; all layout math lives there.
$from = new DateTime((new DateTime(@$_GET['date'] ?: 'today', $tz))->modify('monday this week')->format('Y-m-d'));
$to = (clone $from)->modify('+7 days');

$filtered = isset($_GET['filter']);
$visible = $filtered ? (array) (@$_GET['visible'] ?: []) : null;
$show = fn($extra) => !$filtered || isset($_GET[$extra]);
$show_filtered_events = isset($_GET['filtered']);

$appointments = [];
foreach(\calendar\appointments($from, $to) as $a) {
  if($visible !== null && !in_array($a['calendar_id'] ?? $a['subscription_id'], $visible)) continue;

  $hidden_by_filter = !empty($a['subscription_filter']) && stripos($a['title'], $a['subscription_filter']) === false;
  if($hidden_by_filter) {
    if(!$show_filtered_events) continue;
    $a['going'] = false;
  }

  if(!$hidden_by_filter && !$show('declined') && !cast_boolean($a['going'])) continue;
  $appointments[] = $a;
}

$timed = array_filter($appointments, fn($a) => !cast_boolean($a['all_day']));
$all_day_appointments = array_filter($appointments, fn($a) => cast_boolean($a['all_day']));

if($show('tasks')) {
  $tasks = \calendar\task_deadlines($from, $to);
  $timed = array_merge($timed, array_filter($tasks, fn($a) => !cast_boolean($a['all_day'])));
  $all_day_appointments = array_merge($all_day_appointments, array_filter($tasks, fn($a) => cast_boolean($a['all_day'])));
}

if($show('birthdays')) $all_day_appointments = array_merge($all_day_appointments, \calendar\birthdays($from, $to));
$all_day = \calendar\all_day_lanes($all_day_appointments, $from);
$days = array_map('\calendar\layout', \calendar\day_segments($timed, $from, $to));
$travel = $show('travel') ? \calendar\travel_bands($timed, $from, $to) : [];
$timings = $show('timings') ? \calendar\timing_lines($from, $to) : [];
$habits = $show('habits') ? \habits\calendar($from, $to) : [];

$now_date = $now->format('Y-m-d');
$now_top = ((int) $now->format('H') * 60 + (int) $now->format('i')) / 1440 * 100;

$color = fn($a) => esc_attr($a['calendar_color'] ?? $a['subscription_color'] ?? '#cccccc');
$task_icon = fn($a) => ['done' => 'fa-check', 'blocked' => 'fa-xmark'][@$a['task_status']] ?? 'fa-alarm-clock';

$sidebar_right = UI_SIDEBAR_POSITION == 'right';

?>
<div class="page-header">
  <div class="calendar-week__lead">
    <?php if(!$sidebar_right): ?>
      <div class="calendar-week__tools">
        <button type="button" title="Filters" data-sidebar><i class="fa-regular fa-sidebar-flip"></i></button>
      </div>
    <?php endif ?>
    <h1 class="page-header__title"><strong><?= $from->format('F') ?></strong> <?= $from->format('Y') ?></h1>
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
    <?php if($sidebar_right): ?>
      <button type="button" title="Filters" data-sidebar><i class="fa-regular fa-sidebar-flip"></i></button>
    <?php endif ?>
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

  <div class="calendar-week__all-day" data-start="<?= $from->format('Y-m-d') ?>">
    <?php foreach($all_day as $appointment): ?>
      <?php
        $is_birthday = cast_boolean(@$appointment['is_birthday']);
        $is_task = cast_boolean(@$appointment['is_task'])
      ?>
      <article class="appointment appointment--all-day<?= cast_boolean($appointment['going']) ? "" : " appointment--not-going" ?><?= $is_birthday ? " appointment--birthday" : "" ?><?= $is_task ? " appointment--task" : "" ?>"
               <?= !$is_birthday && !$is_task ? 'data-id="' . esc_attr($appointment['id']) . '"' : '' ?>
               <?= $is_task ? 'data-task-id="' . esc_attr($appointment['id']) . '"' : '' ?>
               style="--appointment-column: <?= $appointment['layout']['column'] ?>; --appointment-span: <?= $appointment['layout']['span'] ?>;
                      --appointment-row: <?= $appointment['layout']['row'] ?>;
                      --appointment-color: <?= $color($appointment) ?>">
          <h3 class="appointment__title"><?php if($is_birthday): ?><i class="fa-solid fa-cake-candles"></i> <?php endif ?><?php if($is_task): ?><i class="fa-solid <?= $task_icon($appointment) ?>"></i> <?php endif ?><?= $appointment['title'] ?><?php if(cast_boolean(@$appointment['urgent'])) circle("circle--tight") ?></h3>

          <?php if($appointment['recurrence'] || $appointment['meeting']): ?>
            <span class="appointment__icons">
              <?php if($appointment['recurrence']): ?><i class="fa-solid fa-repeat" title="<?= esc_attr(\recurrence\describe($appointment['recurrence'])) ?>"></i><?php endif ?>
              <?php if($appointment['meeting']): ?><i class="fa-solid fa-video"></i><?php endif ?>
            </span>
          <?php endif ?>
      </article>
    <?php endforeach ?>
  </div>

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
          <div class="day__timing" data-id="<?= esc_attr($timing['id']) ?>" title="<?= esc_attr($timing['title']) ?>"
               style="--timing-top: <?= $timing['top'] ?>; --timing-height: <?= $timing['height'] ?>; --timing-color: <?= esc_attr($timing['color']) ?>">
            <span class="day__timing-dot day__timing-dot--start"><i class="fa-solid fa-clock"></i></span>
            <span class="day__timing-dot day__timing-dot--end"></span>
          </div>
        <?php endforeach ?>

        <?php foreach($day as $appointment): $layout = $appointment['layout']; $is_task = cast_boolean(@$appointment['is_task']) ?>
          <article class="appointment<?= cast_boolean($appointment['going']) ? "" : " appointment--not-going" ?><?= $is_task ? " appointment--task" : "" ?>"
                   <?= $is_task ? 'data-task-id="' . esc_attr($appointment['id']) . '"' : 'data-id="' . esc_attr($appointment['id']) . '"' ?>
                   style="--appointment-top: <?= $layout['top'] ?>;
                          --appointment-height: <?= $layout['height'] ?>;
                          <?= cast_boolean($appointment['going'])
                            ? "--appointment-width: {$layout['width']}; --appointment-left: {$layout['left']};"
                            : "--appointment-inset: {$layout['inset']};" ?>
                          --appointment-color: <?= $color($appointment) ?>">
            <h3 class="appointment__title"><?php if($is_task): ?><i class="fa-solid <?= $task_icon($appointment) ?>"></i> <?php endif ?><?= $appointment['title'] ?><?php if(cast_boolean(@$appointment['urgent'])) circle("circle--tight") ?></h3>

            <?php if($appointment['location']): ?>
              <span class="appointment__location"><?= $appointment['location'] ?></span>
            <?php endif ?>

            <span class="appointment__duration">
              <?php if($is_task): ?>
                <time class="appointment__start" datetime="<?= $appointment['starts_at'] ?>"><?= date("H:i", strtotime($appointment['starts_at'])) ?></time>
              <?php else: ?>
                <time class="appointment__start" datetime="<?= $appointment['starts_at'] ?>"><?= date("H:i", strtotime($appointment['starts_at'])) ?></time><span class="appointment__duration-separator"> – </span><time class="appointment__end" datetime="<?= $appointment['ends_at'] ?>"><?= date("H:i", strtotime($appointment['ends_at'])) ?></time>
              <?php endif ?>
            </span>

            <?php if($appointment['recurrence'] || $appointment['meeting']): ?>
              <span class="appointment__icons">
                <?php if($appointment['recurrence']): ?><i class="fa-solid fa-repeat" title="<?= esc_attr(\recurrence\describe($appointment['recurrence'])) ?>"></i><?php endif ?>
                <?php if($appointment['meeting']): ?><i class="fa-solid fa-video"></i><?php endif ?>
              </span>
            <?php endif ?>

            <?php if(cast_boolean(@$appointment['editable'])): ?>
              <span class="appointment__handle appointment__handle--top"></span>
              <span class="appointment__handle appointment__handle--bottom"></span>
            <?php endif ?>
          </article>
        <?php endforeach ?>
      </section>
    <?php endforeach ?>
  </div>

  <?php if($habits): ?>
    <div class="calendar-week__habits calendar-week__habits--<?= UI_HABITS_POSITION ?>">
      <?php foreach(array_keys($days) as $date): ?>
        <div class="day__habits">
          <?php foreach($habits[$date] as $habit): ?>
            <?php include __DIR__ . "/habits/button.php" ?>
          <?php endforeach ?>
        </div>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</div>
