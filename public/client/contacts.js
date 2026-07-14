// Contact behaviour that keeps state on the client: the existing-address
// picker in the edit form, and clearing the panel when the open contact
// drops out of a re-filtered listing.

const addresses = () =>
  JSON.parse(document.getElementById("addresses-data")?.textContent || "{}");

const add_row = (repeat, address) => {
  const rows = repeat.querySelector(".repeat__rows");
  rows.append(repeat.querySelector("template").content.cloneNode(true));

  const row = rows.lastElementChild;
  for(const [key, value] of Object.entries(address)) {
    const input = row.querySelector(`[name="address_${key}[]"]`);
    if(input && key != "label") input.value = value ?? "";
  }

  repeat.dispatchEvent(new Event("input", { bubbles: true }));
};

const item_for = (line, address) => {
  const item = document.createElement("li");
  item.className = "listing__item address-item";
  item.dataset.line = line;

  if(address.label) {
    const label = document.createElement("strong");
    label.textContent = address.label;
    item.append(label, " — ");
  }

  item.append(line);
  return item;
};

document.addEventListener("input", (e) => {
  const search = e.target.closest?.("[data-address-search]");
  if(!search || e.target != search.querySelector("input")) return;

  const query = e.target.value.trim().toLowerCase();

  search.querySelector("ul").replaceChildren(...Object.entries(addresses())
    .filter(([line, a]) => query && `${line} ${a.label ?? ""}`.toLowerCase().includes(query))
    .map(([line, a]) => item_for(line, a)));
});

document.addEventListener("click", (e) => {
  const toggle = e.target.closest?.("[data-address-search-toggle]");
  if(toggle) {
    const search = toggle.closest("fieldset").querySelector("[data-address-search]");
    search.hidden = !search.hidden;
    search.querySelector("input").value = "";
    search.querySelector("ul").replaceChildren();
    if(!search.hidden) search.querySelector("input").focus();
  }

  const pick = e.target.closest?.("[data-address-search] .address-item");
  if(pick) {
    add_row(pick.closest("fieldset"), addresses()[pick.dataset.line]);
    pick.closest("[data-address-search]").hidden = true;
  }
});

// Deselect the open contact once it drops out of the re-filtered list,
// but only in view mode — never yank it out from under an edit.
document.addEventListener("x-swap", (e) => {
  if(e.target.id != "contacts-list") return;

  const open = document.querySelector("#contacts-panel [data-edit]");
  if(!open) return;

  const url = new URL(open.getAttribute("x-get"), location.href);
  const item = `.contact-item[data-id="${url.searchParams.get("id")}"][data-kind="${url.searchParams.get("kind")}"]`;
  if(!e.target.querySelector(item)) document.getElementById("contacts-panel").replaceChildren();
});
