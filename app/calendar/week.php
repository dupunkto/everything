<?php
  $tz = new DateTimeZone(getenv("TIMEZONE") ?: "Europe/Amsterdam");

  // Defaults to the Monday on or before today. A weeks-from-now offset would
  // be a future enhancement (prev/next anchors already drive this param).
  $week_param = $_GET['week'] ?? null;
  $today_local = (new DateTimeImmutable("now", $tz))->setTime(0, 0);
  $start_of_week = $week_param
    ? (new DateTimeImmutable($week_param . " 00:00:00", $tz))
    : $today_local->modify("monday this week");

  $days = [];
  for($i = 0; $i < 7; $i++) {
    $days[] = $start_of_week->modify("+$i day");
  }

  $range_from = $start_of_week->setTimezone(new DateTimeZone("UTC"))->format("c");
  $range_to = $start_of_week->modify("+7 day")->setTimezone(new DateTimeZone("UTC"))->format("c");

  $all_events = \store\list_appointments_between($range_from, $range_to) ?: [];

  // Place each event onto each day it visually intersects, clamped to the
  // day's [00:00, 24:00) window so multi-day events render as separate
  // blocks per day instead of one giant overflowing div.
  $by_day = [];
  foreach($days as $day) $by_day[$day->format("Y-m-d")] = [];

  foreach($all_events as $event) {
    $start = (new DateTimeImmutable($event['starts_at']))->setTimezone($tz);
    $end = (new DateTimeImmutable($event['ends_at']))->setTimezone($tz);

    foreach($days as $day) {
      $day_start = $day;
      $day_end = $day->modify("+1 day");
      if($end <= $day_start) continue;
      if($start >= $day_end) continue;

      $visible_start = $start > $day_start ? $start : $day_start;
      $visible_end = $end < $day_end ? $end : $day_end;

      $event['_start_min'] = ($visible_start->getTimestamp() - $day_start->getTimestamp()) / 60;
      $event['_end_min'] = ($visible_end->getTimestamp() - $day_start->getTimestamp()) / 60;
      $event['_display_start'] = $start->format("H:i");
      $event['_display_end'] = $end->format("H:i");

      $by_day[$day->format("Y-m-d")][] = $event;
    }
  }

  // Greedy column packing per day for overlapping (non-all-day) events.
  // Inline because it's purely a render-time concern; if more views need it,
  // promote to neuro/.
  $pack = function(array $events) {
    usort($events, fn($a, $b) =>
      $a['_start_min'] <=> $b['_start_min'] ?: $a['_end_min'] <=> $b['_end_min']);

    $groups = [];
    $current = [];
    $current_end = -INF;
    foreach($events as $i => $e) {
      if($current && $e['_start_min'] >= $current_end) {
        $groups[] = $current;
        $current = [];
        $current_end = -INF;
      }
      $current[] = $i;
      $current_end = max($current_end, $e['_end_min']);
    }
    if($current) $groups[] = $current;

    foreach($groups as $group) {
      $cols = []; // column_index => last_end_min
      $total = 0;
      foreach($group as $i) {
        $col = 0;
        while(isset($cols[$col]) and $cols[$col] > $events[$i]['_start_min']) $col++;
        $cols[$col] = $events[$i]['_end_min'];
        $events[$i]['_col'] = $col;
        $total = max($total, $col + 1);
      }
      foreach($group as $i) $events[$i]['_cols'] = $total;
    }
    return $events;
  };

  $event_style = function($event) {
    $bg = $event['calendar_color'] ?? $event['subscription_color'] ?? "#cccccc";
    $fg = contrast_color($bg);
    $border = $event['color'] ?? null;
    $col = $event['_col'] ?? 0;
    $cols = $event['_cols'] ?? 1;
    $width = (100 / $cols);
    $left = ($col * $width);
    $top = $event['_start_min'] * (48 / 60);
    $height = max(20, ($event['_end_min'] - $event['_start_min']) * (48 / 60));
    $style = sprintf(
      "top:%.2fpx;height:%.2fpx;left:%.4f%%;width:%.4f%%;background:%s;color:%s;",
      $top, $height, $left, $width, esc_attr($bg), esc_attr($fg)
    );
    if($border) $style .= "--appt-color:" . esc_attr($border) . ";";
    return $style;
  };

  $prev_week = $start_of_week->modify("-7 day")->format("Y-m-d");
  $next_week = $start_of_week->modify("+7 day")->format("Y-m-d");
  $current_week_anchor = $today_local->modify("monday this week")->format("Y-m-d");
?>
<header class="week-bar">
  <h2>
    <?= $start_of_week->format("M j") ?>
    &mdash;
    <?= $start_of_week->modify("+6 day")->format("M j, Y") ?>
  </h2>
  <nav class="group">
    <a x-get="/calendar/week?week=<?= $prev_week ?>" x-target="#calendar-week">&larr;</a>
    <a x-get="/calendar/week?week=<?= $current_week_anchor ?>" x-target="#calendar-week">Today</a>
    <a x-get="/calendar/week?week=<?= $next_week ?>" x-target="#calendar-week">&rarr;</a>
  </nav>
</header>

<div class="week-grid">
  <div class="time-gutter">
    <?php for($h = 0; $h < 24; $h++): ?>
      <div class="hour" style="top:<?= $h * 48 ?>px"><?= sprintf("%02d", $h) ?></div>
    <?php endfor ?>
  </div>

  <?php foreach($days as $day):
    $date = $day->format("Y-m-d");
    $is_today = $date == $today_local->format("Y-m-d");

    $all_day = array_filter($by_day[$date], fn($e) => $e['all_day']);
    $timed = $pack(array_values(array_filter($by_day[$date], fn($e) => !$e['all_day'])));
  ?>
    <div class="day <?= $is_today ? 'today' : '' ?>" data-date="<?= $date ?>">
      <header class="day-header">
        <div class="day-name"><?= $day->format("D") ?></div>
        <div class="day-num"><?= $day->format("j") ?></div>
        <div class="all-day-row">
          <?php foreach($all_day as $event):
            $bg = $event['calendar_color'] ?? $event['subscription_color'] ?? "#cccccc";
            $fg = contrast_color($bg);
            $classes = ["appt", "all-day"];
            if($event['circled']) $classes[] = "circled";
            if(!$event['going']) $classes[] = "not-going";
            $style = "background:" . esc_attr($bg) . ";color:" . esc_attr($fg) . ";";
            if($event['color']) $style .= "--appt-color:" . esc_attr($event['color']) . ";";
          ?>
            <div class="<?= implode(" ", $classes) ?>" style="<?= $style ?>" data-id="<?= esc_attr($event['id']) ?>">
              <?= esc_inner($event['title']) ?>
            </div>
          <?php endforeach ?>
        </div>
      </header>

      <div class="day-body">
        <?php foreach($timed as $event):
          $classes = ["appt"];
          if($event['circled']) $classes[] = "circled";
          if(!$event['going']) $classes[] = "not-going";
        ?>
          <div class="<?= implode(" ", $classes) ?>" style="<?= $event_style($event) ?>" data-id="<?= esc_attr($event['id']) ?>">
            <div class="appt-head">
              <strong class="appt-title"><?= esc_inner($event['title']) ?></strong>
              <span class="appt-time"><?= $event['_display_start'] ?>&ndash;<?= $event['_display_end'] ?></span>
            </div>
            <?php if($event['location']): ?>
              <div class="appt-location"><?= esc_inner($event['location']) ?></div>
            <?php endif ?>
          </div>
        <?php endforeach ?>

        <?php if($is_today):
          $now = new DateTimeImmutable("now", $tz);
          $now_top = ($now->format("G") * 60 + (int)$now->format("i")) * (48 / 60);
        ?>
          <div class="now-line" style="top:<?= $now_top ?>px"></div>
        <?php endif ?>
      </div>
    </div>
  <?php endforeach ?>
</div>
