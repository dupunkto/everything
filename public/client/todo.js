// Blocked and backlogged tasks want a reason: ask for one before the
// status change posts, and drop the change when the prompt is cancelled.
// Capture-phase, so this runs before xhtml posts the form.

document.addEventListener("change", (e) => {
  const editor = e.target.closest?.(".todo-editor");
  if(!editor) return;

  // A comment on its own is sent with the Comment button, not on blur.
  if(e.target.matches("[name=comment]")) return e.stopPropagation();
  if(!e.target.matches("[name=status]")) return;

  const question = {
    blocked: "Why was this task blocked?",
    backlog: "Why was this task backlogged?",
  }[e.target.value];
  if(!question) return;

  const reason = prompt(question);

  if(reason == null) {
    e.stopPropagation();
    // Fall back to the stored status, which the last render marked selected.
    e.target.value = [...e.target.options].find((o) => o.defaultSelected)?.value;
  }
  else editor.querySelector("[name=comment]").value = reason;
}, true);
