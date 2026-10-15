<?php

  $week_start = \statistics\period_start(@$_GET['date'], 'week');
  $week_end = $week_start->modify("+1 week");
  $rows = \statistics\overview('week', $week_start, $week_end, \store\list_quotas());

?>
<header class="page-header">
  <h2>Week <?= (int)$week_start->format('W') ?></h2>
  <nav class="view-nav">
    <button type="button" title="Previous week" x-get="/tracker/statistics/week?date=<?= $week_start->modify('-1 week')->format('Y-m-d') ?>" x-target="#statistics-week">&larr;</button>
    <button type="button" x-get="/tracker/statistics/week" x-target="#statistics-week">This week</button>
    <button type="button" title="Next week" x-get="/tracker/statistics/week?date=<?= $week_start->modify('+1 week')->format('Y-m-d') ?>" x-target="#statistics-week">&rarr;</button>
  </nav>
</header>
<?php include __DIR__ . "/table.php" ?>
