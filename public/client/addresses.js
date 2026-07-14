const form = document.getElementById("address-editor");
const list = document.getElementById("addresses-list");
const cancel = form.querySelector("[data-address-cancel]");
const submit = form.querySelector("[data-address-submit]");
const directions = form.querySelector("[data-address-directions]");

const field = (name) => form.elements[`addr_${name}`];

const set_mode = (mode) => {
  submit.textContent = mode == 'edit' ? "Save address" : "Add address";
  cancel.hidden = mode != 'edit';
  directions.hidden = mode != 'edit' || !directions.href;
};

const clear_form = () => {
  form.reset();
  field('id').value = "";
  directions.removeAttribute("href");
  set_mode('insert');
};

const fill_form = (item) => {
  for(const key of ["id", "label", "streetName", "streetNumber", "postalCode", "city", "province", "country", "timezone", "note"]) {
    const input = field(key.replace(/[A-Z]/g, (c) => `_${c.toLowerCase()}`));
    if(input) input.value = item.dataset[key] ?? "";
  }

  if(item.dataset.mapsUrl) directions.href = item.dataset.mapsUrl;
  else directions.removeAttribute("href");

  set_mode('edit');
};

list.addEventListener("click", (e) => {
  const item = e.target.closest(".address-item");
  if(!item) return;

  fill_form(item);
});

cancel.addEventListener("click", clear_form);

form.addEventListener("submit", () => {
  if(field('id').value) setTimeout(clear_form);
});
