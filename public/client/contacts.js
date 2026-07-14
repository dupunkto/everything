// Repeatable form rows and existing-address search for the contacts app.

const address_data = () => {
  try { return JSON.parse(document.getElementById("addresses-data")?.textContent || "{}"); }
  catch { return {}; }
};

const address_text = (line, address) => `${line} ${address.label ?? ""}`.toLowerCase();

const fill_address_row = (row, address) => {
  for(const [key, value] of Object.entries(address)) {
    if(key == "label") continue;

    const input = row.querySelector(`[name="address_${key}[]"]`);
    if(input) input.value = value ?? "";
  }
};

const add_address_row = (repeat, address = null) => {
  const template = repeat.querySelector(".repeat__template");
  const rows = repeat.querySelector(".repeat__rows");
  rows.append(template.content.cloneNode(true));

  const row = rows.lastElementChild;
  if(address) fill_address_row(row, address);
  return row;
};

const address_item = (line, address) => {
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

const render_address_search = (search) => {
  const input = search.querySelector("[data-address-search-input]");
  const results = search.querySelector("[data-address-search-results]");
  const query = input.value.trim().toLowerCase();

  results.replaceChildren();
  if(query == "") return;

  Object.entries(address_data())
    .filter(([line, address]) => address_text(line, address).includes(query))
    .forEach(([line, address]) => results.append(address_item(line, address)));
};

document.addEventListener("click", (e) => {
  const add = e.target.closest("[data-add]");
  if(add) {
    add_address_row(add.closest("[data-repeat]"));
    return;
  }

  const toggle = e.target.closest("[data-address-search-toggle]");
  if(toggle) {
    const search = toggle.closest("[data-repeat]").querySelector("[data-address-search]");
    const input = search.querySelector("[data-address-search-input]");
    const results = search.querySelector("[data-address-search-results]");

    search.hidden = !search.hidden;
    input.value = "";
    results.replaceChildren();
    if(!search.hidden) input.focus();
    return;
  }

  const address = e.target.closest("[data-address-search-results] .address-item");
  if(address) {
    const repeat = address.closest("[data-repeat]");
    add_address_row(repeat, address_data()[address.dataset.line]);
    address.closest("[data-address-search]").hidden = true;
    return;
  }

  const remove = e.target.closest("[data-remove]");
  if(remove) {
    const row = remove.closest(".repeat__row");
    // Only confirm when the row's value field actually holds something.
    if(remove.dataset.repeatKind != "Address" && row.querySelector("[data-value]")?.value.trim() && !confirm("Are you sure?")) return;
    row.remove();
  }
});

document.addEventListener("input", (e) => {
  const input = e.target.closest("[data-address-search-input]");
  if(!input) return;

  render_address_search(input.closest("[data-address-search]"));
});
