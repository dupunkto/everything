// Blocked statuses want a reason: ask for one before the status change posts,
// and drop the change when the prompt is cancelled.

document.addEventListener("change", (e) => {
  const editor = e.target.closest?.(".todo-editor");
  if(!editor) return;

  // A comment on its own is sent with the Comment/amend button, not on blur.
  if(e.target.matches("[name=comment]")) return e.stopPropagation();

  // Everything other than blocked status changes follows regular flow. Continue.
  if(!e.target.matches("[name=status]")) return;
  if(e.target.value != "blocked") return;

  const reason = prompt("Why was this task blocked?");

  if(reason == null) {
    e.stopPropagation();
    // Fall back to the stored status, which the last render marked selected.
    e.target.value = [...e.target.options].find((o) => o.defaultSelected)?.value;
  }
  else editor.querySelector("[name=comment]").value = reason;
}, true);
