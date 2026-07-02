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

      $start = new DateTime('monday this week', $timezone);
      $end = new DateTime('monday next week', $timezone);
    ?>

    <main class="wide">
      <section id="calendar-new" x-get="/calendar/new"></section>
      <section id="calendar-week"
        x-get="/calendar/week?from=<?= $start->setTime(0, 0, 0)->format("Y-m-d H:i:s") ?>&to=<?= $end->setTime(0, 0, 0)->format("Y-m-d H:i:s") ?>"></section>
    </main>
  </body>
</html>
