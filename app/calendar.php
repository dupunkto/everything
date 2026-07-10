<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Calendar</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/calendar.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>

    <?php
      $timezone = new DateTimeZone(getenv("TIMEZONE") ?: "Europe/Amsterdam");
      $today = new DateTime('today', $timezone);
    ?>

    <main class="wide calendar-page">
      <!-- <section id="calendar-new" x-get="/calendar/new"></section> -->
      <section id="calendar-week"
        x-get="/calendar/week?date=<?= $today->format("Y-m-d") ?>"></section>
    </main>
  </body>
</html>
