<header>
  <h1><a href="<?= CANONICAL ?>/">Everything</a></h1>
  <nav class="group">
    <a href="<?= CANONICAL ?>/settings">Settings</a>
  </nav>
</header>

<nav>
  <ul>
    <li><a href="/calendar"><i class="fa-regular fa-calendar"></i> <span>Calendar</span></a></li>
    <li><a href="/todo"><i class="fa-regular fa-box-check"></i> <span>ToDo</span></a></li>
    <li><a href="/notes"><i class="fa-regular fa-notebook"></i> <span>Notes</span></a></li>
    <li><a href="/wishlist"><i class="fa-regular fa-book-heart"></i> <span>Wishlist</span></a></li>
    <li><a href="/contacts"><i class="fa-regular fa-address-book"></i> <span>Contacts</span></a></li>
  </ul>
</nav>

<script>
  (() => {
    const nav = document.currentScript.previousElementSibling;
    const KEY = "nav-expanded";

    // Restore the expanded state before first paint, without animating.
    if (sessionStorage.getItem(KEY)) {
      const root = document.documentElement;
      root.classList.add("no-anim");
      nav.classList.add("expanded");
      requestAnimationFrame(() => requestAnimationFrame(() => root.classList.remove("no-anim")));

      // If the cursor already left during the load, collapse on the first move.
      document.addEventListener("mousemove", (e) => {
        if (!nav.contains(e.target)) {
          nav.classList.remove("expanded");
          sessionStorage.removeItem(KEY);
        }
      }, { once: true });
    }

    nav.addEventListener("mouseenter", () => {
      nav.classList.add("expanded");
      sessionStorage.setItem(KEY, "1");
    });
    nav.addEventListener("mouseleave", () => {
      nav.classList.remove("expanded");
      sessionStorage.removeItem(KEY);
    });
  })();
</script>
