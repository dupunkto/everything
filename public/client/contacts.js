// Contact behaviour that keeps state on the client: the existing-address
// picker in the edit form, retaining the list scroll position, and clearing
// the panel when the open contact drops out of a re-filtered listing.

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

let restoring_history = false;
let list_scroll = 0;

const contact_state_url = (state) => {
  const url = new URL(location.href);
  url.searchParams.delete("edit");
  url.searchParams.delete("view");
  url.searchParams.delete("kind");

  if(state?.id) {
    url.searchParams.set("kind", state.kind);
    url.searchParams.set(state.mode, state.id);
  }

  return url;
};

const push_contact_state = (state) => {
  if(restoring_history) return;

  const url = contact_state_url(state);
  if(url.href != location.href) history.pushState(null, "", url);
};

const restore_contact_state = async () => {
  const panel = document.getElementById("contacts-panel");
  const query = new URLSearchParams(location.search);
  const kind = query.get("kind") || "person";
  const mode = query.has("edit") ? "edit" : query.has("view") ? "view" : null;
  const id = mode && query.get(mode);

  restoring_history = true;

  if(id) {
    const path = mode == "edit" ? "/contacts/edit" : "/contacts/detail";
    const url = `${path}?kind=${encodeURIComponent(kind)}&id=${encodeURIComponent(id)}`;
    const response = await fetch(url, { headers: { Accept: "text/html" } });
    xhtml.swap(panel, await response.text());
  }
  else {
    panel.replaceChildren();
  }

  restoring_history = false;
};

document.addEventListener("scroll", (e) => {
  if(e.target.matches?.(".contacts__list")) list_scroll = e.target.scrollTop;
}, true);

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
  if(e.target.id == "contacts-panel") {
    const state = e.target.querySelector("[data-contact-state]");
    push_contact_state(state && {
      mode: state.dataset.contactState,
      kind: state.dataset.kind,
      id: state.dataset.id,
    });
    return;
  }

  if(e.target.id != "contacts-list") return;

  e.target.querySelector(".contacts__list").scrollTop = list_scroll;

  const open = document.querySelector("#contacts-panel [data-edit]");
  if(!open) return;

  const url = new URL(open.getAttribute("x-get"), location.href);
  const item = `.contact-item[data-id="${url.searchParams.get("id")}"][data-kind="${url.searchParams.get("kind")}"]`;
  if(!e.target.querySelector(item)) {
    document.getElementById("contacts-panel").replaceChildren();
    push_contact_state(null);
  }
});

addEventListener("popstate", restore_contact_state);
