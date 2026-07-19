<?php

  $quotas = \store\list_quotas();
  $has_weekly = array_filter($quotas, fn($quota) => $quota['period'] == 'week');
  $has_monthly = array_filter($quotas, fn($quota) => $quota['period'] == 'month');

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Tracker quotas</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/tracker.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main main--semi-wide quota-overview">
      <?php if(!$quotas): ?>
        <p class="placeholder">No quotas set</p>
      <?php endif ?>

      <?php if($has_weekly): ?>
        <section id="quota-week" class="quota-overview__section" x-get="/tracker/quotas/week"><?php fragment("tracker/quotas/week") ?></section>
      <?php endif ?>

      <?php if($has_monthly): ?>
        <section id="quota-month" class="quota-overview__section" x-get="/tracker/quotas/month"><?php fragment("tracker/quotas/month") ?></section>
      <?php endif ?>

      <?php if($quotas): ?>
        <section id="quota-total" class="quota-overview__section" x-get="/tracker/quotas/total"><?php fragment("tracker/quotas/total") ?></section>
      <?php endif ?>
    </main>
  </body>
</html>
