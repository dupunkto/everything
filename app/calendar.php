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

      // Calendars and subscriptions share the sidebar as a single alphabetical
      // list; each is just a coloured, toggleable event source.
      $sources = array_merge(
        \store\list_calendars() ?: [],
        \store\list_subscriptions() ?: []
      );

      usort($sources, fn($a, $b) => strcasecmp($a['title'], $b['title']));
    ?>

    <main class="wide calendar-page">
      <!-- <section id="calendar-new" x-get="/calendar/new"></section> -->
      <section id="calendar-week"></section>

      <aside class="calendar-sidebar">
        <form id="calendar-filters" class="calendar-sidebar__filters">
          <div class="calendar-sidebar__scroll">
            <section class="calendar-sidebar__group">
              <h2 class="calendar-sidebar__heading">Calendars</h2>
              <ul class="calendar-sidebar__list">
                <?php foreach($sources as $source): ?>
                  <li>
                    <label class="calendar-sidebar__item">
                      <input type="checkbox" name="visible" value="<?= esc_attr($source['id']) ?>"
                        checked style="accent-color: <?= esc_attr($source['color'] ?? '#cccccc') ?>">
                      <span class="calendar-sidebar__label">
                        <?= esc_inner($source['title']) ?><?php if($source['subtitle']): ?> <small>(<?= esc_inner($source['subtitle']) ?>)</small><?php endif ?>
                      </span>
                    </label>
                  </li>
                <?php endforeach ?>
              </ul>
            </section>

            <section class="calendar-sidebar__group">
              <h2 class="calendar-sidebar__heading">Other</h2>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="hide_not_going">
                <span class="calendar-sidebar__label">Hide not going</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="hide_travel">
                <span class="calendar-sidebar__label">Hide travel time</span>
              </label>
            </section>
          </div>

          <section class="calendar-sidebar__group calendar-sidebar__footer">
            <input type="range" name="zoom" min="1.5" max="8" step="0.5" value="3"
              class="calendar-sidebar__zoom">
          </section>
        </form>
      </aside>
    </main>

    <script>
      (() => {
        const page = document.querySelector(".calendar-page");
        const week = document.getElementById("calendar-week");
        const form = document.getElementById("calendar-filters");
        const FILTER_KEY = "calendar-filters";
        const SIDEBAR_KEY = "calendar-sidebar-open";
        const ZOOM_KEY = "calendar-zoom";

        let currentDate = <?= json_encode($today->format("Y-m-d")) ?>;

        const state = () => {
          try { return JSON.parse(localStorage.getItem(FILTER_KEY)) || {}; }
          catch { return {}; }
        };

        const hiddenIds = () =>
          [...form.querySelectorAll("input[name=visible]:not(:checked)")].map(b => b.value);

        // Reflect persisted filter state onto the checkboxes before the first fetch.
        const restoreFilters = () => {
          const { hidden = [], hideNotGoing = false, hideTravel = false } = state();
          for(const box of form.querySelectorAll("input[name=visible]"))
            box.checked = !hidden.includes(box.value);
          form.elements.hide_not_going.checked = hideNotGoing;
          form.elements.hide_travel.checked = hideTravel;
        };

        const saveFilters = () => localStorage.setItem(FILTER_KEY, JSON.stringify({
          hidden: hiddenIds(),
          hideNotGoing: form.elements.hide_not_going.checked,
          hideTravel: form.elements.hide_travel.checked
        }));

        const buildUrl = (path) => {
          const params = new URLSearchParams({ date: currentDate });
          const hidden = hiddenIds();
          if(hidden.length) params.set("hidden", hidden.join(","));
          if(form.elements.hide_not_going.checked) params.set("not_going", "1");
          if(form.elements.hide_travel.checked) params.set("hide_travel", "1");
          return path + "?" + params;
        };

        // Both endpoints return the same week fragment; sync mutates first.
        const fetchWeek = async (path = "/calendar/week") => {
          week.setAttribute("x-loading", "");
          const response = await fetch(buildUrl(path), { headers: { Accept: "text/html" } });
          week.innerHTML = await response.text();
          week.removeAttribute("x-loading");

          // innerHTML doesn't execute inserted <script> tags; recreate them so
          // the week fragment's own scripts (scroll restore) run. Mirrors xhtml.
          for(const dead of week.querySelectorAll("script")) {
            const script = document.createElement("script");
            for(const attr of dead.attributes) script.setAttribute(attr.name, attr.value);
            script.textContent = dead.textContent;
            dead.replaceWith(script);
          }

          if(window.hydrate) hydrate(week);
        };

        // Zoom sets the hour height on the persistent page element, so it
        // cascades into every fetched week fragment without reapplying.
        const zoom = form.elements.zoom;
        const applyZoom = () => page.style.setProperty("--hour-height", zoom.value + "rem");

        const restoreZoom = () => {
          const saved = localStorage.getItem(ZOOM_KEY);
          if(saved) zoom.value = saved;
          applyZoom();
        };

        zoom.addEventListener("input", () => {
          applyZoom();
          localStorage.setItem(ZOOM_KEY, zoom.value);
        });

        const toggleSidebar = (open) => {
          open = open ?? !page.classList.contains("sidebar-open");
          page.classList.toggle("sidebar-open", open);
          sessionStorage.setItem(SIDEBAR_KEY, open ? "1" : "");
        };

        // Navigation and the sidebar toggle live inside the swappable week
        // fragment, so listen on the persistent container via delegation.
        week.addEventListener("click", (event) => {
          const nav = event.target.closest("[data-date]");
          if(nav) {
            currentDate = nav.dataset.date;
            // Today drops the saved scroll so the week's own heuristic
            // re-centres on the now-line instead of preserving position.
            if(nav.hasAttribute("data-today")) delete week.dataset.scrollTop;
            fetchWeek();
            return;
          }
          const sync = event.target.closest("[data-sync]");
          if(sync) {
            // Spins until the fetched fragment replaces the icon.
            sync.querySelector("i")?.classList.add("fa-spin");
            fetchWeek("/calendar/sync");
            return;
          }
          if(event.target.closest("[data-sidebar-toggle]")) toggleSidebar();
        });

        form.addEventListener("change", (event) => {
          if(event.target === zoom) return; // zoom is CSS-only, no refetch
          saveFilters();
          fetchWeek();
        });

        toggleSidebar(!!sessionStorage.getItem(SIDEBAR_KEY));
        restoreFilters();
        restoreZoom();
        fetchWeek();
      })();
    </script>
  </body>
</html>
