<?php

  $month_start = \quotas\period_start(@$_GET['date'], 'month');
  $month_end = $month_start->modify("+1 month");
  $rows = \quotas\overview('month', $month_start, $month_end, \store\list_quotas());

?>
<header class="page-header">
  <h2><?= $month_start->format('F') ?></h2>
  <nav class="view-nav">
    <button type="button" title="Previous month" x-get="/tracker/quotas/month?date=<?= $month_start->modify('-1 month')->format('Y-m-d') ?>" x-target="#quota-month">&larr;</button>
    <button type="button" x-get="/tracker/quotas/month" x-target="#quota-month">This month</button>
    <button type="button" title="Next month" x-get="/tracker/quotas/month?date=<?= $month_start->modify('+1 month')->format('Y-m-d') ?>" x-target="#quota-month">&rarr;</button>
  </nav>
</header>
<?php include __DIR__ . "/table.php" ?>
