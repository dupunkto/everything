<?php

  $week_start = \quotas\period_start(@$_GET['date'], 'week');
  $week_end = $week_start->modify("+1 week");
  $rows = \quotas\overview('week', $week_start, $week_end, \store\list_quotas());

?>
<header class="page-header">
  <h2>Week <?= (int)$week_start->format('W') ?></h2>
  <nav class="view-nav">
    <button type="button" title="Previous week" x-get="/tracker/quotas/week?date=<?= $week_start->modify('-1 week')->format('Y-m-d') ?>" x-target="#quota-week">&larr;</button>
    <button type="button" x-get="/tracker/quotas/week" x-target="#quota-week">This week</button>
    <button type="button" title="Next week" x-get="/tracker/quotas/week?date=<?= $week_start->modify('+1 week')->format('Y-m-d') ?>" x-target="#quota-week">&rarr;</button>
  </nav>
</header>
<?php include __DIR__ . "/table.php" ?>
