<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Tracker statistics</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/tracker.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main main--semi-wide quota-overview">
      <section id="quota-week" class="quota-overview__section" x-get="/tracker/quotas/week"><?php fragment("tracker/quotas/week") ?></section>
      <section id="quota-month" class="quota-overview__section" x-get="/tracker/quotas/month"><?php fragment("tracker/quotas/month") ?></section>
      <section id="quota-total" class="quota-overview__section" x-get="/tracker/quotas/total"><?php fragment("tracker/quotas/total") ?></section>
    </main>
  </body>
</html>
