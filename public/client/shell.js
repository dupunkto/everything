// Restore the persisted sidebar state on <html> before first paint, so the
// expanded sidebar is styled from the start and never animates open on load.
// This relies on the script being parser-blocking (no defer/async).
if (localStorage.getItem("nav-expanded")) {
  document.documentElement.classList.add("nav--expanded");
}

// The toggle button is rendered later in the body, so bind once it exists.
addEventListener("DOMContentLoaded", () => {
  document.querySelector(".nav__toggle").addEventListener("click", () => {
    if (document.documentElement.classList.toggle("nav--expanded")) localStorage.setItem("nav-expanded", "1");
    else localStorage.removeItem("nav-expanded");
  });
});
