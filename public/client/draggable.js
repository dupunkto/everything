let dragged_item = null;

function sortable_item(target) {
  return target.closest?.("[data-reorder-url] [data-order-id]");
}

function sortable_list(target) {
  return target.closest?.("[data-reorder-url]");
}

document.addEventListener("pointerdown", (e) => {
  const handle = e.target.closest?.("[data-drag-handle]");
  const item = handle && sortable_item(handle);
  if(!item) return;

  item.draggable = true;
});

document.addEventListener("pointerup", () => {
  if(dragged_item) return;
  document.querySelectorAll("[data-order-id][draggable=true]").forEach((item) => {
    item.draggable = false;
  });
});

document.addEventListener("dragstart", (e) => {
  const item = sortable_item(e.target);
  if(!item || !item.draggable) return;

  dragged_item = item;
  item.classList.add("order-item--dragging");
  e.dataTransfer.effectAllowed = "move";
  e.dataTransfer.setData("text/plain", item.dataset.orderId);
});

document.addEventListener("dragover", (e) => {
  const list = sortable_list(e.target);
  if(!list || !dragged_item) return;

  e.preventDefault();

  const item = sortable_item(e.target);
  if(!item) return list.append(dragged_item);
  if(item == dragged_item) return;

  const {top, height} = item.getBoundingClientRect();
  item[height / 2 > e.clientY - top ? "before" : "after"](dragged_item);
});

document.addEventListener("drop", async (e) => {
  const list = sortable_list(e.target);
  if(!list || !dragged_item) return;

  e.preventDefault();

  const body = new URLSearchParams();
  list.querySelectorAll("[data-order-id]").forEach((item) => {
    body.append("ids[]", item.dataset.orderId);
  });

  const response = await fetch(list.dataset.reorderUrl, {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body,
  });

  if(response.status == 204) return;

  const target = document.querySelector(list.dataset.reorderTarget);
  if(!target) return;

  const html = await response.text();
  if(window.xhtml?.swap) xhtml.swap(target, html);
  else target.innerHTML = html;
});

document.addEventListener("dragend", () => {
  document.querySelectorAll(".order-item--dragging").forEach((item) => {
    item.classList.remove("order-item--dragging");
    item.draggable = false;
  });

  dragged_item = null;
});
