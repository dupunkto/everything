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
