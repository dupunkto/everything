<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Calendar</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/calendar.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="wide" id="calendar-main">
      <section id="calendar-week" x-get="/calendar/week"></section>
      <div id="calendar-popup" hidden></div>
    </main>

    <script type="module">
      // The popup is a single floating panel that the server fills with one
      // of three fragments: a new-event form, an event detail card, or an
      // event editor. JS only does the positioning + open/close lifecycle;
      // every fragment ships rendered HTML from PHP.
      //
      // In a future zhtml.js, the open/close + click-outside-to-dismiss
      // could be declared on the panel itself (e.g. `z-popup`, with
      // `z-dismiss-outside` and a paired `z-open-at="mouse"`). For now this
      // is the minimum viable wiring.

      const main = document.getElementById("calendar-main");
      const popup = document.getElementById("calendar-popup");

      // Setting innerHTML doesn't run any <script> tags inside the fragment
      // (HTML spec). Mirror xhtml.js: rip each dead script out and replace
      // with a freshly-created equivalent so the browser executes it.
      const reinjectScripts = (root) => {
        for(const dead of root.querySelectorAll("script")) {
          const live = document.createElement("script");
          for(const attr of dead.attributes) live.setAttribute(attr.name, attr.value);
          live.textContent = dead.textContent;
          dead.replaceWith(live);
        }
      };

      const openPopupAt = (clientX, clientY, html) => {
        popup.innerHTML = html;
        popup.hidden = false;

        // Measure after insertion so we can clamp inside the viewport.
        const { width, height } = popup.getBoundingClientRect();
        const margin = 8;
        const x = Math.min(clientX, window.innerWidth - width - margin);
        const y = Math.min(clientY, window.innerHeight - height - margin);
        popup.style.left = Math.max(margin, x) + "px";
        popup.style.top = Math.max(margin, y) + "px";

        // Hydrate xhtml-driven children of the freshly inserted fragment so
        // x-get/x-post inside the popup work without a page reload, then
        // re-execute fragment scripts (per-form behaviors live there).
        if(window.hydrate) window.hydrate(popup);
        reinjectScripts(popup);
      };

      // Exposed globally so server-rendered fragments can call it via an
      // inline <script >__closePopup()</ script> after a successful save.
      window.__closePopup = () => {
        popup.hidden = true;
        popup.innerHTML = "";
      };

      // Pixels per hour. Mirrors the CSS custom property --hour-px on the
      // week view; keep in sync if you change one.
      const HOUR_PX = 48;

      const loadFragment = async (url) => {
        const response = await fetch(url, { headers: { Accept: "text/html" } });
        return response.text();
      };

      main.addEventListener("click", async (event) => {
        const appt = event.target.closest(".appt");
        if(appt) {
          const html = await loadFragment(`/calendar/popup?id=${appt.dataset.id}`);
          openPopupAt(event.clientX, event.clientY, html);
          return;
        }

        // Click on empty space inside the day body becomes a "new event at
        // this time" affordance. We compute the clicked minute from the y
        // offset against HOUR_PX and snap to the nearest 15 minutes.
        const body = event.target.closest(".day-body");
        if(body) {
          const rect = body.getBoundingClientRect();
          const offsetY = event.clientY - rect.top;
          const minutes = Math.max(0, Math.round((offsetY / HOUR_PX) * 60 / 15) * 15);
          const date = body.closest(".day").dataset.date;
          const params = new URLSearchParams({ date, minute: String(minutes) });
          const html = await loadFragment(`/calendar/new?${params}`);
          openPopupAt(event.clientX, event.clientY, html);
          return;
        }
      });

      document.addEventListener("click", (event) => {
        if(popup.hidden) return;
        if(popup.contains(event.target)) return;
        if(event.target.closest(".appt, .day-body")) return; // handled above
        window.__closePopup();
      });

      document.addEventListener("keydown", (event) => {
        if(event.key == "Escape") window.__closePopup();
      });

      // Auto-scroll the week so the current hour is roughly centered after
      // the week view loads. Done via a MutationObserver on the week host
      // because the actual grid is injected by xhtml after the initial GET.
      const observer = new MutationObserver(() => {
        const grid = document.querySelector(".week-grid");
        if(!grid) return;
        const now = new Date();
        const target = (now.getHours() + now.getMinutes() / 60) * HOUR_PX;
        grid.scrollTop = Math.max(0, target - grid.clientHeight / 2);
      });
      observer.observe(document.getElementById("calendar-week"), { childList: true, subtree: true });
    </script>
  </body>
</html>
