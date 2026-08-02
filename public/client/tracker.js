const fmt_date = (d) => d.toLocaleDateString("en-CA");
const fmt_time = (d) => d.toTimeString().slice(0, 8);

const parse_local = (date, time) => date && time ? new Date(`${date}T${time}`) : null;

const fmt_duration = (ms) => {
  const s = Math.max(0, Math.floor(ms / 1000));
  const h = Math.floor(s / 3600);
  const m = Math.floor(s % 3600 / 60);
  return `${h}:${String(m).padStart(2, "0")}:${String(s % 60).padStart(2, "0")}`;
};

const close_time_popups = () => {
  for(const popup of document.querySelectorAll("[data-time-popup]")) popup.hidden = true;
};

document.addEventListener("click", (event) => {
  if(!event.target.closest(".tracker-form__timer")) close_time_popups();
});

document.addEventListener("keydown", (event) => {
  if(event.key == "Escape") close_time_popups();
});

zhtml.directive("z-timer", (form) => {
  const record = form.querySelector("[data-record]");
  const duration = form.querySelector("[data-duration]");
  const popup = form.querySelector("[data-time-popup]");
  const description = form.elements.description;
  const start_fields = [form.elements.start_date, form.elements.start_time];
  const end_fields = [form.elements.end_date, form.elements.end_time];
  const fields = [...start_fields, ...end_fields];

  let tick = null;

  const manual = () => fields.every((field) => field.value);
  const started = () => localStorage.getItem("start_timing");

  const set_start = (date) => {
    form.elements.start_date.value = fmt_date(date);
    form.elements.start_time.value = fmt_time(date);
  };

  const set_end = (date) => {
    form.elements.end_date.value = fmt_date(date);
    form.elements.end_time.value = fmt_time(date);
  };

  const render = () => {
    const start = started();
    const begin = start ? new Date(start) : parse_local(form.elements.start_date.value, form.elements.start_time.value);
    const end = parse_local(form.elements.end_date.value, form.elements.end_time.value);

    record.innerHTML = start ? "⏸" : manual() ? "&rarr;" : "▶";
    record.type = start || !manual() ? "button" : "submit";
    duration.textContent = fmt_duration(begin ? ((start ? new Date() : end) - begin) : 0);

    for(const field of end_fields) field.disabled = !!start;
    if(start) set_start(begin);

    clearInterval(tick);
    tick = start ? setInterval(render, 1000) : null;
  };

  duration.addEventListener("click", () => popup.hidden = !popup.hidden);

  record.addEventListener("click", () => {
    const start = started();

    if(start) {
      set_end(new Date());
      localStorage.removeItem("start_timing");
      localStorage.removeItem("description");
      for(const field of end_fields) field.disabled = false;
      form.requestSubmit();
    }
    else if(!manual()) {
      const now = new Date();
      localStorage.setItem("start_timing", now.toISOString());
      set_start(now);
    }

    render();
  });

  form.addEventListener("input", () => {
    localStorage.setItem("description", description.value);

    if(started()) {
      const begin = parse_local(form.elements.start_date.value, form.elements.start_time.value);
      if(begin) localStorage.setItem("start_timing", begin.toISOString());
    }

    render();
  });

  form.addEventListener("submit", () => {
    if(started()) return;
    localStorage.removeItem("description");
  });

  description.value = localStorage.getItem("description") ?? "";
  render();
});

(() => {
  // Elements are looked up at event time: document swaps replace them,
  // while this module runs only once per browser page load.
  const listing = () => document.getElementById("tracker-listing");
  const editor = () => document.querySelector(".tracker-popup-editor");

  let editing = editor()?.querySelector('[name="id"]')?.value;
  let initial_edit = !!editing;

  let restoring_history = false;

  const push_editor_state = (id) => {
    if(restoring_history) return;

    const url = new URL(location.href);
    if(id) url.searchParams.set("edit", id);
    else url.searchParams.delete("edit");

    if(url.href != location.href) history.pushState(null, "", url);
  };

  const restore_editor_state = () => {
    if(!listing()) return; // popstate on some other page
    const id = new URLSearchParams(location.search).get("edit");

    restoring_history = true;
    id ? open_editor(id) : close_editor();
    restoring_history = false;
  };

  const anchor = () =>
    editing && listing()?.querySelector(`.tracker-list__item[data-id="${CSS.escape(editing)}"]`);

  const position_editor = () => {
    const popup = editor();
    const target = anchor();
    if(!target || !popup || popup.hidden) return;

    const gap = parseFloat(getComputedStyle(document.documentElement).fontSize);
    const rect = target.getBoundingClientRect();
    const form = document.getElementById("tracker-form");
    const form_bottom = form ? form.getBoundingClientRect().bottom + gap : gap;

    const left = Math.max(gap, Math.min(rect.left + rect.width / 2 - popup.offsetWidth / 2, innerWidth - gap - popup.offsetWidth));
    const min_top = Math.max(gap, form_bottom);
    const max_top = innerHeight - gap - popup.offsetHeight;
    const top = Math.max(min_top, Math.min(rect.top + rect.height / 2 - popup.offsetHeight / 4, max_top));

    popup.style.left = left + "px";
    popup.style.top = top + "px";
  };

  const reveal_editor = () => {
    const popup = editor();
    const target = anchor();
    if(!popup || !target) return close_editor();

    requestAnimationFrame(() => {
      const target_box = target.getBoundingClientRect();
      const scroll_box = listing().getBoundingClientRect();
      listing().scrollTop += target_box.top - scroll_box.top
        - (listing().clientHeight - target_box.height) / 2;

      requestAnimationFrame(() => {
        popup.hidden = false;
        position_editor();
      });
    });
  };

  const close_editor = () => {
    const popup = editor();
    if(popup) {
      popup.hidden = true;
      popup.innerHTML = "";
    }
    editing = null;

    // Unpin the closed editor without resetting the current view.
    const url = new URL(listing().getAttribute("x-get"), location.origin);
    url.searchParams.delete("id");
    listing().setAttribute("x-get", url.pathname + url.search);

    push_editor_state(null);
  };

  const open_editor = async (id) => {
    editing = id;
    push_editor_state(id);
    const response = await fetch("/tracker/edit?id=" + encodeURIComponent(id), { headers: { Accept: "text/html" } });
    const popup = editor();
    if(!popup) return;
    xhtml.swap(popup, await response.text());
    popup.hidden = false;
    position_editor();
  };

  document.addEventListener("dblclick", (event) => {
    const item = event.target.closest?.("#tracker-listing .tracker-list__item[data-id]");
    if(item) open_editor(item.dataset.id);
  });

  document.addEventListener("click", async (event) => {
    const popup = event.target.closest?.(".tracker-popup-editor");
    if(!popup) return;

    if(event.target.closest("[data-close]")) return close_editor();

    const dates = event.target.closest("[data-show-dates]");
    if(dates) {
      for(const field of popup.querySelectorAll(".tracker-editor__date")) field.hidden = false;
      dates.hidden = true;
    }
  });

  document.addEventListener("x-swap", (event) => {
    if(event.target.closest?.(".tracker-popup-editor"))
      return void xhtml.refresh("#tracker-listing");

    if(event.target.id == "tracker-listing") {
      // A fresh listing with ?edit in the URL is a deep link (or a soft
      // navigation to one): open the editor once, then keep it anchored.
      const wanted = new URLSearchParams(location.search).get("edit");
      if(wanted && !editing) return void open_editor(wanted);

      if(initial_edit) {
        initial_edit = false;
        reveal_editor();
        return;
      }
      anchor() ? position_editor() : close_editor();
      return;
    }

    if(event.target.id == "tracker-new") position_editor();
  });

  document.addEventListener("scroll", position_editor, true);
  addEventListener("resize", position_editor);

  document.addEventListener("click", (event) => {
    const popup = editor();
    if(!popup || popup.hidden || popup.contains(event.target)) return;
    if(!event.target.isConnected) return; // the click removed its target (eg. a tag badge), so contains() can't place it
    if(event.target.closest(".tracker-list__item[data-id]")) return; // clicks of a dblclick
    close_editor();
  });

  addEventListener("popstate", restore_editor_state);

  document.addEventListener("keydown", (event) => {
    if(event.key == "Escape" && listing()) close_editor();
  });
})();
