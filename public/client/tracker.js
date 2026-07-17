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
  const creator = document.getElementById("tracker-new");
  const listing = document.getElementById("tracker-listing");
  const editor = document.querySelector(".tracker-popup-editor");
  let editing = null;
  let pending_edit = new URLSearchParams(location.search).get("edit");

  const anchor = () =>
    editing && listing.querySelector(`.tracker-list__item[data-id="${CSS.escape(editing)}"]`);

  const position_editor = () => {
    const target = anchor();
    if(!target || editor.hidden) return;

    const gap = parseFloat(getComputedStyle(document.documentElement).fontSize);
    const rect = target.getBoundingClientRect();
    const form = document.getElementById("tracker-form");
    const form_bottom = form ? form.getBoundingClientRect().bottom + gap : gap;

    const left = Math.max(gap, Math.min(rect.left + rect.width / 2 - editor.offsetWidth / 2, innerWidth - gap - editor.offsetWidth));
    const min_top = Math.max(gap, form_bottom);
    const max_top = innerHeight - gap - editor.offsetHeight;
    const top = Math.max(min_top, Math.min(rect.top + rect.height / 2 - editor.offsetHeight / 4, max_top));

    editor.style.left = left + "px";
    editor.style.top = top + "px";
  };

  const close_editor = () => {
    editor.hidden = true;
    editor.innerHTML = "";
    editing = null;
  };

  const open_editor = async (id) => {
    editing = id;
    const response = await fetch("/tracker/edit?id=" + encodeURIComponent(id), { headers: { Accept: "text/html" } });
    xhtml.swap(editor, await response.text());
    editor.hidden = false;
    position_editor();
  };

  listing.addEventListener("dblclick", (event) => {
    const item = event.target.closest(".tracker-list__item[data-id]");
    if(item) open_editor(item.dataset.id);
  });

  editor.addEventListener("click", async (event) => {
    if(event.target.closest("[data-close]")) return close_editor();

    const dates = event.target.closest("[data-show-dates]");
    if(dates) {
      for(const field of editor.querySelectorAll(".tracker-editor__date")) field.hidden = false;
      dates.hidden = true;
      return;
    }

    const button = event.target.closest("[data-delete]");
    if(!button || !confirm("Are you sure?")) return;

    await fetch("/tracker/delete?id=" + encodeURIComponent(button.dataset.delete), { method: "DELETE" });
    close_editor();
    xhtml.refresh("#tracker-listing");
  });

  editor.addEventListener("x-swap", () => xhtml.refresh("#tracker-listing"));
  listing.addEventListener("x-swap", () => {
    if(pending_edit) {
      open_editor(pending_edit);
      pending_edit = null;
      return;
    }

    anchor() ? position_editor() : close_editor();
  });
  creator.addEventListener("x-swap", position_editor);
  document.addEventListener("scroll", position_editor, true);
  addEventListener("resize", position_editor);

  document.addEventListener("click", (event) => {
    if(editor.hidden || editor.contains(event.target)) return;
    if(!event.target.isConnected) return; // the click removed its target (eg. a tag badge), so contains() can't place it
    if(event.target.closest(".tracker-list__item[data-id]")) return; // clicks of a dblclick
    close_editor();
  });

  document.addEventListener("keydown", (event) => {
    if(event.key == "Escape") close_editor();
  });
})();
