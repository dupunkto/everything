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

      // New events land on the default calendar; its colour tints the
      // drag-to-create ghost so the preview already looks like the real thing.
      $default_color = @\store\get_calendar(DEFAULT_CALENDAR)['color'] ?: '#cccccc';

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
                <input type="checkbox" name="show_travel" checked>
                <span class="calendar-sidebar__label">Travel time</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="show_tasks" checked>
                <span class="calendar-sidebar__label">Deadlines</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="show_timings" checked>
                <span class="calendar-sidebar__label">Timings</span>
              </label>
              <label class="calendar-sidebar__item">
                <input type="checkbox" name="show_not_going" checked>
                <span class="calendar-sidebar__label">Declined events</span>
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
        const NEW_EVENT_COLOR = <?= json_encode($default_color) ?>;

        const state = () => {
          try { return JSON.parse(localStorage.getItem(FILTER_KEY)) || {}; }
          catch { return {}; }
        };

        const hiddenIds = () =>
          [...form.querySelectorAll("input[name=visible]:not(:checked)")].map(b => b.value);

        // Reflect persisted filter state onto the checkboxes before the first fetch.
        const restoreFilters = () => {
          const { hidden = [], showNotGoing = true, showTravel = true, showTasks = true, showTimings = true } = state();
          for(const box of form.querySelectorAll("input[name=visible]"))
            box.checked = !hidden.includes(box.value);
          form.elements.show_not_going.checked = showNotGoing;
          form.elements.show_travel.checked = showTravel;
          form.elements.show_tasks.checked = showTasks;
          form.elements.show_timings.checked = showTimings;
        };

        const saveFilters = () => localStorage.setItem(FILTER_KEY, JSON.stringify({
          hidden: hiddenIds(),
          showNotGoing: form.elements.show_not_going.checked,
          showTravel: form.elements.show_travel.checked,
          showTasks: form.elements.show_tasks.checked,
          showTimings: form.elements.show_timings.checked
        }));

        const buildUrl = (path) => {
          const params = new URLSearchParams({ date: currentDate });
          const hidden = hiddenIds();
          if(hidden.length) params.set("hidden", hidden.join(","));
          if(!form.elements.show_not_going.checked) params.set("not_going", "1");
          if(!form.elements.show_travel.checked) params.set("hide_travel", "1");
          if(!form.elements.show_tasks.checked) params.set("no_tasks", "1");
          if(!form.elements.show_timings.checked) params.set("no_timings", "1");
          return path + "?" + params;
        };

        // innerHTML doesn't execute inserted <script> tags; recreate them so a
        // fetched fragment's own scripts run. Mirrors xhtml.
        const runScripts = (root) => {
          for(const dead of root.querySelectorAll("script")) {
            const script = document.createElement("script");
            for(const attr of dead.attributes) script.setAttribute(attr.name, attr.value);
            script.textContent = dead.textContent;
            dead.replaceWith(script);
          }
        };

        // Both endpoints return the same week fragment; sync mutates first.
        const fetchWeek = async (path = "/calendar/week") => {
          week.setAttribute("x-loading", "");
          const response = await fetch(buildUrl(path), { headers: { Accept: "text/html" } });
          week.innerHTML = await response.text();
          week.removeAttribute("x-loading");

          runScripts(week);
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

        // Appointment editor: a popup fetched on click and floated next to the
        // clicked appointment. Every change is saved and the week re-fetched
        // live (preserving filters/zoom); Cancel replays the pre-edit state.
        const editor = document.createElement("div");
        editor.className = "calendar-editor";
        editor.hidden = true;
        page.appendChild(editor);

        let editingId = null;

        const closeEditor = () => {
          editor.hidden = true;
          editor.innerHTML = "";
          editingId = null;
        };

        const postEdit = (body) => fetch("/calendar/edit", {
          method: "POST", headers: { Accept: "text/html" }, body
        });

        // The popup tracks the appointment while the week scrolls; after a live
        // re-fetch the anchor node is fresh, so it's re-resolved by id each time.
        const positionEditor = () => {
          const anchor = week.querySelector(`.appointment[data-id="${CSS.escape(editingId)}"]`);
          if(!anchor) return;

          const rect = anchor.getBoundingClientRect();
          const gap = 8;

          let left = rect.right + gap;
          if(left + editor.offsetWidth > window.innerWidth - gap)
            left = rect.left - gap - editor.offsetWidth;
          left = Math.max(gap, left);

          // Centred on the appointment vertically, but clamped so the popup
          // stays fully visible as the appointment scrolls past either edge:
          // the viewport bottom, and the top of the scrollable calendar area
          // (so it never rides up over the day headers). Top wins if both
          // clamps fight (editor taller than the space).
          const days = week.querySelector(".calendar-week__days");
          const minTop = (days ? days.getBoundingClientRect().top : 0) + gap;

          let top = rect.top + rect.height / 2 - editor.offsetHeight / 2;
          if(top + editor.offsetHeight > window.innerHeight - gap)
            top = window.innerHeight - gap - editor.offsetHeight;
          top = Math.max(minTop, top);

          editor.style.left = left + "px";
          editor.style.top = top + "px";
        };

        const openEditor = async (anchor) => {
          editingId = anchor.dataset.id;

          const response = await fetch("/calendar/edit?id=" + encodeURIComponent(editingId),
            { headers: { Accept: "text/html" } });

          editor.innerHTML = await response.text();
          runScripts(editor);

          editor.hidden = false;
          positionEditor();

          const form = editor.querySelector("form");
          if(!form) return;

          form.addEventListener("submit", (event) => event.preventDefault());

          form.addEventListener("change", async () => {
            await postEdit(new URLSearchParams(new FormData(form)));
            await fetchWeek();
            positionEditor();
          });

          form.querySelector("[data-delete]")?.addEventListener("click", async () => {
            const id = editingId;
            closeEditor();
            await fetch("/calendar/delete", {
              method: "POST", headers: { Accept: "text/html" },
              body: new URLSearchParams({ id })
            });
            fetchWeek();
          });
        };

        document.addEventListener("click", (event) => {
          if(editor.hidden || editor.contains(event.target)) return;
          if(event.target.closest(".appointment[data-id]")) return; // don't close on the clicks of a dblclick
          closeEditor();
        });

        document.addEventListener("keydown", (event) => {
          if(event.key === "Escape") closeEditor();
        });

        // Scroll fires on the week's inner container, which is swapped on every
        // fetch; capture it on the persistent wrapper instead.
        week.addEventListener("scroll", () => {
          if(!editor.hidden) positionEditor();
        }, true);

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

        // The editor opens on double-click; double-clicking the open
        // appointment again toggles it shut.
        week.addEventListener("dblclick", (event) => {
          const appointment = event.target.closest(".appointment[data-id]");
          if(!appointment) return;

          if(!editor.hidden && appointment.dataset.id === editingId) closeEditor();
          else openEditor(appointment);
        });

        // Drag the top/bottom edge of a calendar-owned appointment to change
        // its start/end time, snapped to 10 minutes. The edge is moved live for
        // feedback; on drop the new times are POSTed and the week re-fetched so
        // the column layout re-settles. Handles only exist on resizable events.
        const SNAP = 10;
        const DAY = 1440;

        const pad = (n) => String(n).padStart(2, "0");
        const hhmm = (min) => `${pad(Math.floor(min / 60) % 24)}:${pad(min % 60)}`;

        week.addEventListener("mousedown", (event) => {
          if(!editor.hidden) return; // no resizing while the editor is open

          const handle = event.target.closest(".appointment__handle");
          if(!handle) return;

          event.preventDefault();

          const article = handle.closest(".appointment");
          const day = article.closest(".day");
          const top = handle.classList.contains("appointment__handle--top");

          const startEl = article.querySelector(".appointment__start");
          const endEl = article.querySelector(".appointment__end");

          const minutesOf = (el) => {
            const [h, m] = el.getAttribute("datetime").slice(11, 16).split(":");
            return (+h) * 60 + (+m);
          };

          const startMin0 = minutesOf(startEl), endMin0 = minutesOf(endEl);
          let startMin = startMin0, endMin = endMin0;

          const paint = () => {
            article.style.setProperty("--appointment-top", startMin / DAY * 100);
            article.style.setProperty("--appointment-height", (endMin - startMin) / DAY * 100);
            startEl.textContent = hhmm(startMin);
            endEl.textContent = hhmm(endMin);
          };

          const onMove = (e) => {
            const rect = day.getBoundingClientRect();
            let min = (e.clientY - rect.top) / rect.height * DAY;
            min = Math.max(0, Math.min(DAY, Math.round(min / SNAP) * SNAP));

            if(top) startMin = Math.min(min, endMin - SNAP);
            else endMin = Math.max(min, startMin + SNAP);

            paint();
          };

          const onUp = async () => {
            document.removeEventListener("mousemove", onMove);
            document.removeEventListener("mouseup", onUp);

            if(startMin === startMin0 && endMin === endMin0) return;

            const date = day.dataset.date;
            // An end at 24:00 lands on midnight of the following day.
            const endDate = endMin >= DAY
              ? new Date(new Date(date + "T00:00:00").getTime() + 86400000).toLocaleDateString("en-CA")
              : date;

            await fetch("/calendar/resize", {
              method: "POST", headers: { Accept: "text/html" },
              body: new URLSearchParams({
                id: article.dataset.id,
                start_date: date, start_time: hhmm(startMin),
                end_date: endDate, end_time: hhmm(endMin >= DAY ? 0 : endMin)
              })
            });

            fetchWeek();
          };

          document.addEventListener("mousemove", onMove);
          document.addEventListener("mouseup", onUp);
        });

        // Drag across empty space in a day to block out a new appointment. The
        // dragged range is previewed as a ghost; on drop a bare "New event" is
        // created on the default calendar and its editor popped open. A plain
        // click (no real drag) makes nothing.
        week.addEventListener("mousedown", (event) => {
          if(!editor.hidden) return; // no creating while the editor is open
          if(event.button !== 0) return;
          if(event.target.closest(".appointment")) return; // that's a drag/edit

          const day = event.target.closest(".day");
          if(!day) return;

          event.preventDefault();

          const rect = day.getBoundingClientRect();
          const at = (clientY) => {
            const min = (clientY - rect.top) / rect.height * DAY;
            return Math.max(0, Math.min(DAY, Math.round(min / SNAP) * SNAP));
          };

          const anchorMin = at(event.clientY);
          let startMin = anchorMin, endMin = anchorMin;

          const ghost = document.createElement("div");
          ghost.className = "day__ghost";
          ghost.hidden = true;
          ghost.style.setProperty("--appointment-color", NEW_EVENT_COLOR);
          day.appendChild(ghost);

          const paint = () => {
            ghost.hidden = endMin - startMin < SNAP;
            ghost.style.setProperty("--appointment-top", startMin / DAY * 100);
            ghost.style.setProperty("--appointment-height", (endMin - startMin) / DAY * 100);
          };

          const onMove = (e) => {
            const min = at(e.clientY);
            startMin = Math.min(anchorMin, min);
            endMin = Math.max(anchorMin, min);
            paint();
          };

          const onUp = async () => {
            document.removeEventListener("mousemove", onMove);
            document.removeEventListener("mouseup", onUp);

            if(endMin - startMin < SNAP) { ghost.remove(); return; } // a click

            const date = day.dataset.date;
            // An end at 24:00 lands on midnight of the following day.
            const endDate = endMin >= DAY
              ? new Date(new Date(date + "T00:00:00").getTime() + 86400000).toLocaleDateString("en-CA")
              : date;

            const response = await fetch("/calendar/create", {
              method: "POST", headers: { Accept: "text/plain" },
              body: new URLSearchParams({
                start_date: date, start_time: hhmm(startMin),
                end_date: endDate, end_time: hhmm(endMin >= DAY ? 0 : endMin)
              })
            });

            if(!response.ok) { ghost.remove(); return; }
            const id = (await response.text()).trim();

            // The ghost is left in place: fetchWeek swaps the whole fragment
            // (ghost included) atomically, so the block never blinks out
            // between creation and the real event rendering.
            await fetchWeek();

            const anchor = week.querySelector(`.appointment[data-id="${CSS.escape(id)}"]`);
            if(anchor) openEditor(anchor);
          };

          document.addEventListener("mousemove", onMove);
          document.addEventListener("mouseup", onUp);
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
