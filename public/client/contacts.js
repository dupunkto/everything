// Repeatable form rows (add/remove) and address autofill for the contacts app.
// Delegated on document so it keeps working across xhtml panel swaps.

const address_data = () => {
  try { return JSON.parse(document.getElementById("addresses-data")?.textContent || "{}"); }
  catch { return {}; }
};

document.addEventListener("click", (e) => {
  const add = e.target.closest("[data-add]");
  if(add) {
    const repeat = add.closest("[data-repeat]");
    const template = repeat.querySelector(".repeat__template");
    repeat.querySelector(".repeat__rows").append(template.content.cloneNode(true));
    return;
  }

  const remove = e.target.closest("[data-remove]");
  if(remove) {
    const row = remove.closest(".repeat__row");
    // Only confirm when the row's value field actually holds something.
    if(row.querySelector("[data-value]")?.value.trim() && !confirm("Are you sure?")) return;
    row.remove();
  }
});

// Picking an existing address from the datalist fills the row's fields.
document.addEventListener("input", (e) => {
  const pick = e.target.closest("[data-address-pick]");
  if(!pick) return;

  const address = address_data()[pick.value];
  if(!address) return;

  const row = pick.closest(".repeat__row, form") ?? document;
  for(const key of ["street_name", "street_number", "postal_code", "city", "province", "country", "timezone"]) {
    const input = row.querySelector(`[name="addr_${key}[]"], [name="addr_${key}"]`);
    if(input) input.value = address[key] ?? "";
  }
});
