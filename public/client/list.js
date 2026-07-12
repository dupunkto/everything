// Shortcuts for ToDo and wishlist.

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
