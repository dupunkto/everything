// Bound once per browser page load: everything is delegated and looks
// its elements up at event time, so revived copies (document swaps
// re-execute classic scripts) must not double-bind.
if(!window.draggable_bound) {
window.draggable_bound = true;

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
  item.dataset.dragging = "";
  e.dataTransfer.effectAllowed = "move";
  e.dataTransfer.setData("text/plain", item.dataset.orderId);
});

document.addEventListener("dragover", (e) => {
  const list = sortable_list(e.target);
  if(!list || !dragged_item) return;

  e.preventDefault();

  const next = [...list.children]
    .filter((item) => item.matches("[data-order-id]") && item != dragged_item)
    .find((item) => {
      const {top, height} = item.getBoundingClientRect();
      return e.clientY < top + height / 2;
    });

  if(next) next.before(dragged_item);
  else list.append(dragged_item);
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
  document.querySelectorAll("[data-dragging]").forEach((item) => {
    delete item.dataset.dragging;
    item.draggable = false;
  });

  dragged_item = null;
});

}
