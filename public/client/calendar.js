// This file contains behaviour for the calendar application that cannot be
// easily expressed declaratively using xhtml and zhtml. It is split into
// four sections: helpers, editor popup, drag-to-move/resize/create,
// and scroll retention on navigation.

// Everything operates on the #calendar-view element, which survives innerHTML
// swaps by xhtml, and should work on any given future view, as long as they
// adhere to the .day and .appointment markup conventions.

(() => {
  const view = document.getElementById("calendar-view");
  const editor = document.querySelector(".calendar-editor");
  const page = document.querySelector(".calendar-page");

  const ready = () => requestAnimationFrame(() => page.classList.add("calendar-page--ready"));
  if(document.readyState == "loading") addEventListener("DOMContentLoaded", ready, { once: true });
  else ready();

  const DAY = 1440, SNAP = 10;

  // Helpers

  const pad = (n) => String(n).padStart(2, "0");
  const hhmm = (min) => `${pad(Math.floor(min / 60) % 24)}:${pad(min % 60)}`;
  const date_add = (date, days) => {
    const [year, month, day] = date.split("-").map(Number);
    return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10);
  };

  const fit_circles = () => {
    for(const ring of view.querySelectorAll(".appointment__title .circle")) {
      const title = ring.parentElement;

      const range = document.createRange();
      range.setStart(title, 0);
      range.setEndBefore(ring);
      const text = range.getBoundingClientRect();
      const base = title.getBoundingClientRect();

      const pad_x = Math.max(12, text.width * 0.14);
      const pad_y = Math.max(8, text.height * 0.22);

      ring.style.left = `${text.left - base.left - pad_x}px`;
      ring.style.top = `${text.top - base.top - pad_y}px`;
      ring.style.width = `${text.width + pad_x * 2}px`;
      ring.style.height = `${text.height + pad_y * 2}px`;
    }
  };

  const minute_at = (day, y) => {
    const rect = day.getBoundingClientRect();
    const min = (y - rect.top) / rect.height * DAY;
    return Math.max(0, Math.min(DAY, Math.round(min / SNAP) * SNAP));
  };

  const minutes = (el) => {
    const [h, m] = el.getAttribute("datetime").slice(11, 16).split(":");
    return +h * 60 + +m;
  };

  // An end at 24:00 lands on midnight of the following day.
  const range = (date, start, end) => ({
    start_date: date,
    start_time: hhmm(start),
    end_date: end >= DAY ? date_add(date, 1) : date,
    end_time: hhmm(end >= DAY ? 0 : end),
  });

  const post = (url, params) => fetch(url, {
    method: "POST",
    headers: { Accept: "text/html" },
    body: new URLSearchParams(params),
  });

  const drag = (move, drop) => {
    const up = () => {
      document.removeEventListener("mousemove", move);
      document.removeEventListener("mouseup", up);
      drop();
    };
    document.addEventListener("mousemove", move);
    document.addEventListener("mouseup", up);
  };

  // Editor popup

  let editing = null;

  const anchor = () =>
    editing && view.querySelector(`.appointment[data-id="${CSS.escape(editing)}"]`);

  const close_editor = () => {
    editor.hidden = true;
    editor.innerHTML = "";
    editing = null;
  };

  const position_editor = () => {
    const target = anchor();
    if(!target) return;

    const rect = target.getBoundingClientRect();
    const gap = 8;

    let left = rect.right + gap;
    if(left + editor.offsetWidth > innerWidth - gap) left = rect.left - gap - editor.offsetWidth;
    left = Math.max(gap, left);

    const days = view.querySelector(".calendar-week__days");
    const min_top = (days ? days.getBoundingClientRect().top : 0) + gap;

    let top = rect.top + rect.height / 2 - editor.offsetHeight / 2;
    top = Math.max(min_top, Math.min(top, innerHeight - gap - editor.offsetHeight));

    editor.style.left = left + "px";
    editor.style.top = top + "px";
  };

  const open_editor = async (appointment) => {
    editing = appointment.dataset.id;

    const response = await fetch("/calendar/edit?id=" + encodeURIComponent(editing),
      { headers: { Accept: "text/html" } });

    xhtml.swap(editor, await response.text());
    editor.hidden = false;
    position_editor();
  };

  const create_appointment = async (params) => {
    const response = await post("/calendar/new", params);
    if(!response.ok) return null;

    const id = (await response.text()).trim();
    await xhtml.refresh("#calendar-view");

    const created = view.querySelector(`.appointment[data-id="${CSS.escape(id)}"]`);
    if(created) open_editor(created);

    return id;
  };

  const create_range = (day, start, end) =>
    create_appointment(range(day.dataset.date, start, end));

  const create_all_day = (date) => create_appointment({
    start_date: date,
    start_time: "00:00",
    end_date: date_add(date, 1),
    end_time: "00:00",
    all_day: 1,
  });

  view.addEventListener("dblclick", (event) => {
    const timing = event.target.closest(".day__timing[data-id]");
    if(timing) {
      location.href = "/tracker?edit=" + encodeURIComponent(timing.dataset.id);
      return;
    }

    const appointment = event.target.closest(".appointment");

    if(appointment) {
      if(appointment.dataset.taskId) {
        location.href = "/todo/edit?id=" + encodeURIComponent(appointment.dataset.taskId);
        return;
      }

      if(appointment.dataset.contactId) {
        location.href = "/contacts?view=" + encodeURIComponent(appointment.dataset.contactId);
        return;
      }

      if(!appointment.dataset.id) return;
      if(!editor.hidden && appointment.dataset.id == editing) close_editor();
      else open_editor(appointment);
      return;
    }

    const all_day = event.target.closest(".calendar-week__all-day");
    if(all_day) {
      const rect = all_day.getBoundingClientRect();
      const column = Math.max(0, Math.min(6, Math.floor((event.clientX - rect.left) / rect.width * 7)));
      create_all_day(date_add(all_day.dataset.start, column));
      return;
    }

    const day = event.target.closest(".day");
    if(!day) return;

    const start = Math.min(23 * 60, Math.floor(minute_at(day, event.clientY) / 60) * 60);
    create_range(day, start, start + 60);
  });

  // Each soft navigation into the calendar revives and re-runs this
  // script against the fresh elements; undo the previous run's
  // document-level listeners before binding new ones.
  window.calendar_teardown?.();

  const close_on_click = (event) => {
    if(editor.hidden || editor.contains(event.target)) return;
    if(event.target.closest(".appointment[data-id]")) return; // the clicks of a dblclick
    close_editor();
  };

  const close_on_escape = (event) => {
    if(event.key == "Escape") close_editor();
  };

  document.addEventListener("click", close_on_click);
  document.addEventListener("keydown", close_on_escape);

  window.calendar_teardown = () => {
    document.removeEventListener("click", close_on_click);
    document.removeEventListener("keydown", close_on_escape);
  };

  // Drag to create, move or resize

  const resize = (event, handle, day) => {
    event.preventDefault();

    const article = handle.closest(".appointment");
    const start_el = article.querySelector(".appointment__start");
    const end_el = article.querySelector(".appointment__end");

    const top = handle.classList.contains("appointment__handle--top");
    const before = [minutes(start_el), minutes(end_el)];
    let [start, end] = before;

    drag((e) => {
      const min = minute_at(day, e.clientY);
      if(top) start = Math.min(min, end - SNAP);
      else end = Math.max(min, start + SNAP);

      article.style.setProperty("--appointment-top", start / DAY * 100);
      article.style.setProperty("--appointment-height", (end - start) / DAY * 100);
      start_el.textContent = hhmm(start);
      end_el.textContent = hhmm(end);
    }, async () => {
      if(start == before[0] && end == before[1]) return;

      await post("/calendar/resize", { id: article.dataset.id, ...range(day.dataset.date, start, end) });
      xhtml.refresh("#calendar-view");
    });
  };

  const move = (event, article, day) => {
    event.preventDefault();

    const start_el = article.querySelector(".appointment__start");
    const end_el = article.querySelector(".appointment__end");
    const before = { day, start: minutes(start_el), end: minutes(end_el) };
    const duration = before.end - before.start;
    const grab = minute_at(day, event.clientY) - before.start;
    let current = before;

    drag((e) => {
      const start = Math.max(0, Math.min(DAY - duration, minute_at(day, e.clientY) - grab));
      const end = start + duration;
      current = { day, start, end };

      article.style.setProperty("--appointment-top", start / DAY * 100);
      article.style.setProperty("--appointment-height", duration / DAY * 100);
      start_el.textContent = hhmm(start);
      end_el.textContent = hhmm(end);
    }, async () => {
      if(current.day == before.day && current.start == before.start) return;

      await post("/calendar/resize", {
        id: article.dataset.id,
        ...range(current.day.dataset.date, current.start, current.end),
      });
      xhtml.refresh("#calendar-view");
    });
  };

  const create = (event, day) => {
    event.preventDefault();

    const origin = minute_at(day, event.clientY);
    let [start, end] = [origin, origin];

    const ghost = document.createElement("div");
    ghost.className = "day__ghost";
    ghost.hidden = true;
    day.appendChild(ghost);

    drag((e) => {
      const min = minute_at(day, e.clientY);
      start = Math.min(origin, min);
      end = Math.max(origin, min);

      ghost.hidden = end - start < SNAP;
      ghost.style.setProperty("--appointment-top", start / DAY * 100);
      ghost.style.setProperty("--appointment-height", (end - start) / DAY * 100);
    }, async () => {
      if(end - start < SNAP) return ghost.remove(); // just a click

      // The ghost stays put: the refresh swaps the whole fragment (ghost
      // included) atomically, so the block never blinks out before the real
      // appointment renders.
      const id = await create_range(day, start, end);
      if(!id) ghost.remove();
    });
  };

  view.addEventListener("mousedown", (event) => {
    if(!editor.hidden || event.button != 0) return;

    const day = event.target.closest(".day");
    if(!day) return;

    const handle = event.target.closest(".appointment__handle");
    const appointment = event.target.closest(".appointment[data-id]");

    if(handle) resize(event, handle, day);
    else if(appointment?.querySelector(".appointment__handle")) move(event, appointment, day);
    else if(!appointment) create(event, day);
  });

  // Scroll retention

  let scroll_top = null;

  view.addEventListener("scroll", (event) => {
    if(!event.target.matches?.(".calendar-week__days")) return;
    scroll_top = event.target.scrollTop;
    if(!editor.hidden) position_editor();
  }, true);

  view.addEventListener("click", (event) => {
    // Today drops the saved scroll so the fresh week recentres on now.
    if(event.target.closest("[data-today]")) scroll_top = null;

    if(event.target.closest("[data-sidebar]")) {
      const box = document.getElementById("calendar-sidebar");
      box.checked = !box.checked;
      box.dispatchEvent(new Event("change", { bubbles: true }));
    }
  });

  view.addEventListener("x-swap", () => {
    fit_circles();

    const days = view.querySelector(".calendar-week__days");

    if(days) {
      const now = days.querySelector(".calendar-week__now");
      const day = days.querySelector(".day");

      if(scroll_top != null) days.scrollTop = scroll_top;
      else if(now) days.scrollTop = now.offsetTop - days.clientHeight / 4;
      else if(day) days.scrollTop = day.offsetHeight / 24 * 6.5; // open at 06:30
    }

    if(editor.hidden) return;
    if(anchor()) position_editor();
    else close_editor(); // the appointment was deleted or moved out of view
  });
})();
