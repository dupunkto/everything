<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Tracker statistics</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/tracker.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main main--semi-wide statistics">
      <section id="statistics-week" class="statistics__section" x-get="/tracker/statistics/week"><?php fragment("tracker/statistics/week") ?></section>
      <section id="statistics-month" class="statistics__section" x-get="/tracker/statistics/month"><?php fragment("tracker/statistics/month") ?></section>
      <section id="statistics-total" class="statistics__section" x-get="/tracker/statistics/total"><?php fragment("tracker/statistics/total") ?></section>
    </main>
  </body>
</html>
