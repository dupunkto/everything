<?php
// The calendar week view.

$appointments = \store\list_appointments($_GET['from'], $_GET['to']);

// Credits to @m1kadev for implementing this in Python originally, for
// a project that shall not be named on legal grounds. Happily stolen:)

$days = [[], [], [], [], [], [], []];

foreach($appointments as $appointment) {
  $start_dt = new DateTime($appointment['starts_at']);
  $end_dt = new DateTime($appointment['ends_at']);

  $day_number = $start_dt->format("N");

  // This enforces a minumum appointment length in the rendering.
  $start_dt->modify('+30 minutes');
  $layout_end = max($end_dt, $start_dt);

  $appointment['day_number'] = $day_number;
  $appointment['layout_end'] = $layout_end;
  $days[$day_number][] = $appointment;
}

foreach($days as $f => $day) {
  usort($day, fn($a, $b) =>
    strcmp($a['starts_at'], $b['starts_at'])
    ?: ($a['calendar_id'] <=> $b['calendar_id'])
    ?: ($a['subscription_id'] <=> $b['subscription_id'])
    ?: strcmp($a['id'], $b['id']));

  $columns = [];

  foreach($day as $appointment) {
    $found = false;

    foreach($columns as $h => $column) {
      $last_appointment = $column[array_key_last($column)];

      if($last_appointment['layout_end'] <= $appointment['starts_at']) {
        $found = true;
        $columns[$h] = $appointment;
      }
    }

    if(!$found) {
      $columns[] = [$appointment];
    }

    $total_columns = $columns ? count($columns) : 1;

    foreach($columns as $i => $column) {
      foreach($column as $j => &$appointment) {
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
        $day_end = $start_dt->modify('+1 day');

        $total_minutes = ($day_end->getTimestamp() - $day_start->getTimestamp()) / 60;
        $start_minutes = ($start_dt->getTimestamp() - $day_start->getTimestamp()) / 60;
        $duration_minutes = ($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;

        $appointment['layout'] = [
          "top" => $start_minutes / $total_minutes * 100,
          "height" => $duration_minutes / $total_minutes * 100,
          "width" => $column_span / $total_columns * 100,
          "left" => $column_index / $total_columns * 100
        ];

        $days[$appointment['day_number']][] = $appointment;
      }
    }
  }
}

foreach($days as $day): ?>
  <section class="day">
    <?php
      // Filter out non-layouted appointments (leftovers from Python algorithm
      // that heavily used mutation by reference, for which semantics in PHP differ).
      $day = array_filter($day, fn($appointment) => array_key_exists('layout', $appointment));
    ?>

    <?php foreach($day as $appointment): ?>
      <article class="appointment"
                style="--appointment-top: <?= $appointment['layout']['top'] ?>;
                      --appointment-height: <?= $appointment['layout']['height'] ?>;
                      --appointment-width: <?= $appointment['layout']['width'] ?>;
                      --appointment-left: <?= $appointment['layout']['left'] ?>">
        <h2 class="appointment__title">
          <?= $appointment['title'] ?>
        </h2>

        $appointment['calendar_color'] ?? $appointment['subscription_color'] ?? "#cccccc";

        <span class="appointment__location">
          <?= $appointment['location'] ?>
        </span>
        
        <span class="appointment__duration">
          <time class="appointment__start" datetime="<?= $appointment['starts_at'] ?>"><?= date("H:i", strtotime($appointment['starts_at'])) ?></time> - <time class="appointment_end" datetime="<?= $appointment['ends_at'] ?>"><?= $appointment['ends_at'] ?></time>
        </span>
      </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
