// This file contains shortcuts and hardcoded behaviour for
// the ToDo and wishlist applications, which could not be easily
// expressed declaratively using xhtml and zhtml.

const gauge = document.createElement("canvas").getContext("2d");

function fit_circle(field) {
  const input = field.querySelector("input");
  const ring = field.querySelector(".circle");
  if(!input || !ring) return;

  const style = getComputedStyle(input);
  gauge.font = `${style.fontStyle} ${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
  const text = input.value || input.placeholder || "";
  const width = gauge.measureText(text).width;
  const origin = parseFloat(style.paddingLeft) + parseFloat(style.borderLeftWidth);

  // The ellipse tapers to points at its ends, so wide text needs proportionally
  // more horizontal room to stay inside the ring; the vertical margin is fixed.
  const pad_x = Math.max(18, width * 0.16), pad_y = 7;
  ring.style.left = `${origin - pad_x}px`;
  ring.style.width = `${width + pad_x * 2}px`;
  ring.style.top = `${-pad_y}px`;
  ring.style.height = `${input.offsetHeight + pad_y * 2}px`;
}

function fit_circles() {
  for(const field of document.querySelectorAll(".circled-field")) {
    fit_circle(field);
    if(!field.dataset.circleBound) {
      field.dataset.circleBound = "1";
      field.querySelector("input")?.addEventListener("input", () => fit_circle(field));
    }
  }
}

fit_circles();

// The editor re-renders by swapping the whole document (e.g. ticking 'Circle'),
// which drops our inline sizing. Observing the document refits the fresh ring.
if(!window.__circle_observer) {
  window.__circle_observer = new MutationObserver(() => {
    if(window.__circle_pending) return;
    window.__circle_pending = true;
    requestAnimationFrame(() => { window.__circle_pending = false; fit_circles(); });
  });
  window.__circle_observer.observe(document, { childList: true, subtree: true });
}

const editors = [
  { form: "todo-editor",   statuses: { b: "backlog", c: "done", u: "todo", s: "nvm" }, revert: "todo",  back: "/todo" },
  { form: "wishlist-editor", statuses: { s: "nvm", c: "bought" }, revert: "dream", back: "/wishlist" },
];

document.addEventListener("keydown", (event) => {
  const editor = editors.find(({ form }) => document.getElementById(form));
  if(!editor) return;

  // Ctrl/Cmd+Enter and Escape save the current state, then return to the listing.
  if(event.key == "Escape" || (event.key == "Enter" && (event.metaKey || event.ctrlKey))) {
    event.preventDefault();
    const form = document.getElementById(editor.form);
    Promise.resolve(form?.x_run?.()).then(() => location.assign(editor.back));
    return;
  }

  // Don't hijack typing in the title, content or comment fields.
  if(document.activeElement?.matches("input, textarea")) return;
  if(!(event.key in editor.statuses)) return;

  event.preventDefault();

  const select = document.getElementById(editor.form).querySelector("[name=status]");
  if(!select) return;

  let target = editor.statuses[event.key];
  if(select.value == target) target = editor.revert;

  // Keep 'prior' in sync so the editor's blocked/backlog comment prompt,
  // if present, can revert a cancelled change.
  select.dataset.prior = select.value;
  select.value = target;
  select.dispatchEvent(new Event("change", { bubbles: true }));
});
