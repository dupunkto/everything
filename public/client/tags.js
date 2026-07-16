// Tag picker (z-tags, see \tags_field): filters the server-rendered
// suggestion list while typing, arrows + enter/tab pick one, backspace
// in an empty input drops the last tag, clicking a badge removes it.

zhtml.directive("z-tags", (root) => {
  const search = root.querySelector(".tags__search");
  const input = root.querySelector(".tags__input");
  const list = root.querySelector(".tags__suggestions");

  const selected = () =>
    [...root.querySelectorAll(".tags__tag input")].map((el) => el.value);

  const visible = () => [...list.querySelectorAll("li:not([hidden])")];
  const active = () => list.querySelector(".tags__suggestion--active");

  // Browsing (arrows in an empty input) shows every option without a
  // query; it ends as soon as a query, a pick or a blur takes over.
  let browse = false;

  // Toggled over every row, visible or not: an item hidden by a
  // refilter must not keep the class, or it would still get picked.
  // Scrolls the list alone to keep the row in view: scrollIntoView
  // would also scroll the page, pulling the list away from the input.
  const activate = (item) => {
    list.querySelectorAll("li").forEach((el) =>
      el.classList.toggle("tags__suggestion--active", el == item));

    if(!item) return;
    const bottom = item.offsetTop + item.offsetHeight - list.clientHeight;
    list.scrollTop = Math.max(bottom, Math.min(list.scrollTop, item.offsetTop));
  };

  // Adding or removing a tag counts as a form edit (input marks the
  // form dirty, change triggers autosaving editors); typing in the
  // search input does not, it never leaves the picker.
  const edited = () => {
    root.dispatchEvent(new Event("input", { bubbles: true }));
    root.dispatchEvent(new Event("change", { bubbles: true }));
  };

  const filter = () => {
    const query = input.value.trim().toLowerCase();
    if(query != "") browse = false;

    for(const item of list.querySelectorAll("li")) {
      item.hidden = !((browse || query != "")
        && (item.dataset.label.toLowerCase().includes(query) || item.dataset.slug.includes(query))
        && !selected().includes(item.dataset.id));
    }

    const items = visible();
    list.hidden = items.length == 0;
    activate(items[0]);
  };

  const add = (item) => {
    const badge = root.querySelector("template").content.cloneNode(true).firstElementChild;
    badge.querySelector("input").value = item.dataset.id;
    badge.style.setProperty("--tag-color", item.dataset.color);
    badge.append(item.dataset.label);
    search.before(badge);
    input.value = "";
    browse = false;
    filter();
    edited();
  };

  const remove = (badge) => {
    badge.remove();
    filter();
    edited();
  };

  input.addEventListener("input", (e) => {
    e.stopPropagation();
    filter();
  });

  input.addEventListener("focus", filter);

  input.addEventListener("focusout", () => {
    browse = false;
    list.hidden = true;
  });

  input.addEventListener("keydown", (e) => {
    const items = visible();

    if(e.key == "ArrowDown" || e.key == "ArrowUp") {
      e.preventDefault();

      // Arrows in an empty, closed input open the full list instead.
      if(!items.length && input.value.trim() == "") {
        browse = true;
        filter();
        if(e.key == "ArrowUp") activate(visible().at(-1));
        return;
      }

      const idx = items.indexOf(active());
      activate(items[idx + (e.key == "ArrowDown" ? 1 : -1)] ?? items.at(e.key == "ArrowDown" ? 0 : -1));
    }

    // Plain enter with the list open picks a match; with unmatched text
    // it is swallowed, so junk never submits the form. Only an empty,
    // closed input keeps the native submit, and mod+enter stays free
    // for the save-and-close shortcut.
    if(e.key == "Enter" && !e.ctrlKey && !e.metaKey && (items.length || input.value.trim() != "")) {
      e.preventDefault();
      const pick = active() ?? items[0];
      if(pick) add(pick);
    }

    if(e.key == "Tab" && (active() ?? items[0])) {
      e.preventDefault();
      add(active() ?? items[0]);
    }

    if(e.key == "Backspace" && input.value == "") {
      const last = [...root.querySelectorAll(".tags__tag")].at(-1);
      if(last) remove(last);
    }
  });

  // Picking happens on pointerdown so focus never leaves the input.
  list.addEventListener("pointerdown", (e) => {
    const item = e.target.closest("li");
    if(item) {
      e.preventDefault();
      add(item);
    }
  });

  root.addEventListener("click", (e) => {
    const badge = e.target.closest(".tags__tag");
    if(badge) remove(badge);
  });
});
