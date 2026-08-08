// Restore the persisted sidebar state on <html> before first paint, so the
// expanded sidebar is styled from the start and never animates open on load.
// This relies on the script being parser-blocking (no defer/async).
if (localStorage.getItem("nav-expanded")) {
  document.documentElement.classList.add("nav--expanded");
}

// The toggle button is rendered later in the body, so bind a generic event
// for the click handler.
if (!window.nav_bound) {
  window.nav_bound = true;

  document.addEventListener("click", (e) => {
    if (!e.target.closest(".nav__toggle")) return;

    if (document.documentElement.classList.toggle("nav--expanded")) localStorage.setItem("nav-expanded", "1");
    else localStorage.removeItem("nav-expanded");
  });
}

// Suppress the browser's native autofill dropdown; the app renders its
// own suggestions where they make sense. Stamped on focus, so swapped-in
// fields are covered too. Fields declaring their own autocomplete win.
if (!window.autocomplete_bound) {
  window.autocomplete_bound = true;

  document.addEventListener("focusin", (e) => {
    if (e.target.matches?.("input:not([autocomplete])")) e.target.setAttribute("autocomplete", "off");
  });
}

if (!window.global_search_bound) {
  window.global_search_bound = true;

  const restore_search_focus = (search) => {
    search?.z_return_focus?.focus?.();
    delete search?.z_return_focus;
  };

  document.addEventListener("click", (e) => {
    if (!e.target.closest("[data-global-search-toggle]")) return;

    const search = document.querySelector("#global-search");
    if (!search || search.hidden) return;

    e.preventDefault();
    e.stopPropagation();
    const input = search.querySelector(".global-search__input");
    input?.focus();
    input?.select();
  }, true);

  document.addEventListener("click", (e) => {
    const search = document.querySelector("#global-search");

    if (e.target.closest("[data-global-search-toggle]")) {
      if (!search) return;
      if (search.hidden) {
        restore_search_focus(search);
        return;
      }
      search.z_return_focus = document.activeElement;
      const input = search.querySelector(".global-search__input");
      input?.focus();
      input?.select();
      return;
    }

    if (e.target.closest(".global-search__backdrop") && search?.hidden) restore_search_focus(search);
  });

  document.addEventListener("submit", (e) => {
    if (!e.target.matches("#global-search-form")) return;

    e.preventDefault();
    document.querySelector("#global-search-results .listing__link")?.click();
  });

  document.addEventListener("keydown", (e) => {
    const search = document.querySelector("#global-search");
    if (e.key == "Escape" && search?.hidden) restore_search_focus(search);
  });
}
