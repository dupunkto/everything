// The record button drives a start/stop timer, persisted in localStorage
// so a running timer survives reloads. A manually completed form is saved
// with the regular submit button instead.

const fmt_date = (d) => d.toLocaleDateString("en-CA");
const fmt_time = (d) => d.toTimeString().slice(0, 8);

zhtml.directive("z-timer", (form) => {
  const record = form.querySelector("[data-record]");
  const save = form.querySelector("[data-save]");
  const description = form.elements.description;
  const times = ["start_date", "start_time", "end_date", "end_time"]
    .map((name) => form.elements[name]);

  const render = () => {
    const start = localStorage.getItem("start_timing");
    const manual = times.every((field) => field.value);

    record.textContent = start ? "⏸" : "▶";
    record.hidden = !start && manual;
    save.hidden = start || !manual;

    if(start) {
      const then = new Date(start);
      form.elements.start_date.value = fmt_date(then);
      form.elements.start_time.value = fmt_time(then);
    }
  };

  record.addEventListener("click", () => {
    if(localStorage.getItem("start_timing")) {
      const now = new Date();
      form.elements.end_date.value = fmt_date(now);
      form.elements.end_time.value = fmt_time(now);

      localStorage.removeItem("start_timing");
      localStorage.removeItem("description");
      form.requestSubmit(); // xhtml posts and resets the form
    }
    else localStorage.setItem("start_timing", new Date().toISOString());

    render();
  });

  form.addEventListener("input", () => {
    localStorage.setItem("description", description.value);
    render();
  });

  description.value = localStorage.getItem("description") ?? "";
  render();
});
