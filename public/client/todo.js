// Blocked statuses want a reason: ask for one before the status change posts,
// and drop the change when the prompt is cancelled.

const ask_for_reason = () => prompt("Why is this blocked?");

document.addEventListener("submit", (e) => {
  // Everything other than blocked status changes follows regular flow. Continue.
  const form = e.target.closest?.("#todo-listing form");
  if(!form || e.submitter?.value != "blocked") return;

  const reason = ask_for_reason();

  if(reason == null) {
    e.preventDefault();
    e.stopPropagation();
  }
  else form.querySelector("[name=comment]").value = reason;
}, true);

document.addEventListener("change", (e) => {
  const editor = e.target.closest?.(".todo-editor");
  if(!editor) return;

  // A comment on its own is sent with the Comment/amend button, not on blur.
  if(e.target.matches("[name=comment]")) return e.stopPropagation();

  // Everything other than blocked status changes follows regular flow. Continue.
  if(!e.target.matches("[name=status]")) return;
  if(e.target.value != "blocked") return;

  const reason = ask_for_reason();

  if(reason == null) {
    e.stopPropagation();
    // Fall back to the stored status, which the last render marked selected.
    e.target.value = [...e.target.options].find((o) => o.defaultSelected)?.value;
  }
  else editor.querySelector("[name=comment]").value = reason;
}, true);
