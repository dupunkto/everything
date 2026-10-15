<?php

  $month_start = \statistics\period_start(@$_GET['date'], 'month');
  $month_end = $month_start->modify("+1 month");
  $rows = \statistics\overview('month', $month_start, $month_end, \store\list_quotas());

?>
<header class="page-header">
  <h2><?= $month_start->format('F') ?></h2>
  <nav class="view-nav">
    <button type="button" title="Previous month" x-get="/tracker/statistics/month?date=<?= $month_start->modify('-1 month')->format('Y-m-d') ?>" x-target="#statistics-month">&larr;</button>
    <button type="button" x-get="/tracker/statistics/month" x-target="#statistics-month">This month</button>
    <button type="button" title="Next month" x-get="/tracker/statistics/month?date=<?= $month_start->modify('+1 month')->format('Y-m-d') ?>" x-target="#statistics-month">&rarr;</button>
  </nav>
</header>
<?php include __DIR__ . "/table.php" ?>
