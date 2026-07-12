<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Calendar</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/calendar.css">
    <script src="<?= CANONICAL ?>/client/calendar.js" defer></script>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>

    <?php
      // The color of the drag-to-create ghost should match the event color,
      // which is the color of the default calendar.
      $ghost_color = @\store\get_calendar(DEFAULT_CALENDAR)['color'] ?: '#cccccc';

      $sources = array_merge(
        \store\list_calendars() ?: [],
        \store\list_subscriptions() ?: []
      );

      usort($sources, fn($a, $b) => strcasecmp($a['title'], $b['title']));
    ?>

    <main class="main main--wide calendar-page" style="--ghost-color: <?= esc_attr($ghost_color) ?>">
      <aside class="calendar-sidebar">
        <form id="calendar-filters" class="calendar-sidebar__filters" z-persist>
          <input type="checkbox" id="calendar-sidebar" name="sidebar" hidden>

          <div class="calendar-sidebar__scroll" x-refresh="#calendar-view" x-on="change">
            <input type="hidden" name="filter" value="1">

            <section class="calendar-sidebar__group">
              <h2 class="calendar-sidebar__heading">Calendars</h2>
              <ul class="calendar-sidebar__list">
                <?php foreach($sources as $source): ?>
                  <li>
                    <label class="calendar-sidebar__item">
                      <input type="checkbox" name="visible[]" value="<?= esc_attr($source['id']) ?>"
                        checked style="accent-color: <?= esc_attr($source['color'] ?? '#cccccc') ?>">
                      <span class="calendar-sidebar__label">
                        <?= esc_inner($source['title']) ?><?php if($source['subtitle']): ?> (<?= esc_inner($source['subtitle']) ?>)<?php endif ?>
                      </span>
                    </label>
                  </li>
                <?php endforeach ?>
              </ul>
            </section>

            <section class="calendar-sidebar__group">
              <h2 class="calendar-sidebar__heading">Other</h2>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="travel" checked>
                <span class="calendar-sidebar__label">Travel time</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="tasks" checked>
                <span class="calendar-sidebar__label">Deadlines</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="timings" checked>
                <span class="calendar-sidebar__label">Timings</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="declined" checked>
                <span class="calendar-sidebar__label">Declined events</span>
              </label>
            </section>
          </div>

          <section class="calendar-sidebar__group calendar-sidebar__footer">
            <input type="range" name="zoom" min="1.5" max="8" step="0.5" value="3"
              class="calendar-sidebar__zoom" z-var="--hour-height" z-unit="rem">
          </section>
        </form>
      </aside>

      <section id="calendar-view" x-get="/calendar/week" x-data="#calendar-filters"></section>

      <div class="calendar-editor" hidden></div>
    </main>
  </body>
</html>
