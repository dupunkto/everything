<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Calendar</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/calendar.css">
    <script src="<?= CANONICAL ?>/client/calendar.js" defer></script>
    <script src="<?= CANONICAL ?>/client/draggable.js" defer></script>
    <script src="<?= CANONICAL ?>/client/circle.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>

    <?php
      // The color of the drag-to-create ghost should match the event color,
      // which is the color of the default calendar.
      $ghost_color = @\store\get_calendar(CALENDAR_DEFAULT_CALENDAR)['color'] ?: '#cccccc';

      $sources = \store\list_sources();
    ?>

    <main class="main main--wide calendar-page <?= UI_SIDEBAR_POSITION == 'right' ? 'calendar-page--sidebar-right' : '' ?>" style="--ghost-color: <?= esc_attr($ghost_color) ?>">
      <aside class="calendar-sidebar">
        <form id="calendar-filters" class="calendar-sidebar__filters" z-persist>
          <input type="checkbox" id="calendar-sidebar" name="sidebar" hidden>

          <div class="calendar-sidebar__scroll" x-refresh="#calendar-view" x-on="change">
            <input type="hidden" name="filter" value="1">

            <section class="calendar-sidebar__group">
              <h2 class="calendar-sidebar__heading">Calendars</h2>
              <ul class="calendar-sidebar__list" data-reorder-url="/calendar/sources/reorder">
                <?php foreach($sources as $source): ?>
                  <li data-order-id="<?= esc_attr($source['type'] . ':' . $source['id']) ?>">
                    <label class="calendar-sidebar__item">
                      <input type="checkbox" name="visible[]" value="<?= esc_attr($source['id']) ?>"
                        checked style="accent-color: <?= esc_attr($source['color'] ?? '#cccccc') ?>">
                      <span class="calendar-sidebar__label" data-drag-handle>
                        <?= esc_inner($source['title']) ?><?php if($source['subtitle']): ?> (<?= esc_inner(mb_strtolower($source['subtitle'])) ?>)<?php endif ?>
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
                <input type="checkbox" name="birthdays" checked>
                <span class="calendar-sidebar__label">Birthdays</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="tasks" checked>
                <span class="calendar-sidebar__label">Deadlines</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="timings" checked>
                <span class="calendar-sidebar__label">Timings</span>
              </label>
              <?php if(\store\list_habits()): ?>
                <label class="calendar-sidebar__item">
                  <input type="checkbox" name="habits" checked>
                  <span class="calendar-sidebar__label">Habits</span>
                </label>
              <?php endif ?>
            </section>

            <section class="calendar-sidebar__group">
              <h2 class="calendar-sidebar__heading">Options</h2>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="declined" checked>
                <span class="calendar-sidebar__label">Declined events</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="filtered">
                <span class="calendar-sidebar__label">Filtered events</span>
              </label>
            </section>
          </div>

          <section class="calendar-sidebar__group calendar-sidebar__footer">
            <input type="range" name="zoom" min="1.5" max="8" step="0.5" value="3"
              class="calendar-sidebar__zoom" z-var="--hour-height" z-unit="em">
          </section>
        </form>
      </aside>

      <!-- The pre-render is a bare-grid skeleton. The explicit
           x-on="load" still fetches the real view through the one
           filtered code path, so appointments never flash before
           persisted filters apply. -->
      <section id="calendar-view" x-get="/calendar/week" x-data="#calendar-filters" x-on="load">
        <?php fragment("calendar/week", ["skeleton" => "1"]) ?>
      </section>

      <div class="popover calendar-editor" hidden></div>
    </main>
  </body>
</html>
