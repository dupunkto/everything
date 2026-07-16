let dragged_tag = null;

function tag_item(target) {
  return target.closest?.(".settings-listing--tags .settings-listing__item");
}

function tag_listing(target) {
  return target.closest?.(".settings-listing--tags");
}

document.addEventListener("pointerdown", (e) => {
  const handle = e.target.closest?.(".settings-editor__drag-handle");
  const item = handle && tag_item(handle);
  if(!item) return;

  item.draggable = true;
});

document.addEventListener("pointerup", () => {
  if(dragged_tag) return;
  document.querySelectorAll(".settings-listing__item[draggable=true]").forEach((item) => {
    item.draggable = false;
  });
});

document.addEventListener("dragstart", (e) => {
  const item = tag_item(e.target);
  if(!item || !item.draggable) return;

  dragged_tag = item;
  item.classList.add("settings-listing__item--dragging");
  e.dataTransfer.effectAllowed = "move";
  e.dataTransfer.setData("text/plain", item.dataset.tagId);
});

document.addEventListener("dragover", (e) => {
  const list = tag_listing(e.target);
  if(!list || !dragged_tag) return;

  e.preventDefault();

  const item = tag_item(e.target);
  if(!item) return list.append(dragged_tag);
  if(item == dragged_tag) return;

  const {top, height} = item.getBoundingClientRect();
  item[height / 2 > e.clientY - top ? "before" : "after"](dragged_tag);
});

document.addEventListener("drop", async (e) => {
  const list = tag_listing(e.target);
  if(!list || !dragged_tag) return;

  e.preventDefault();

  const body = new URLSearchParams();
  list.querySelectorAll(".settings-listing__item").forEach((item) => {
    body.append("ids[]", item.dataset.tagId);
  });

  const response = await fetch(list.dataset.reorderUrl, {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body,
  });

  const html = await response.text();
  const target = document.querySelector("#tags-listing");
  if(window.xhtml?.swap) xhtml.swap(target, html);
  else target.innerHTML = html;
});

document.addEventListener("dragend", () => {
  document.querySelectorAll(".settings-listing__item--dragging").forEach((item) => {
    item.classList.remove("settings-listing__item--dragging");
    item.draggable = false;
  });

  dragged_tag = null;
});
