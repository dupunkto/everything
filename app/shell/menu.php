<header>
  <h1><a href="<?= CANONICAL ?>/">Everything</a></h1>
  <nav class="group">
    <a href="<?= CANONICAL ?>/settings">Settings</a>
  </nav>
</header>

<nav>
  <ul>
    <li><a href="/mail"><i class="fa-regular fa-inbox"></i> <span>Mail</span></a></li>
    <li><a href="/calendar"><i class="fa-regular fa-calendar"></i> <span>Calendar</span></a></li>
    <li><a href="/todo"><i class="fa-regular fa-box-check"></i> <span>ToDo</span></a></li>
    <li><a href="/tracker"><i class="fa-regular fa-timer"></i> <span>Tracker</span></a></li>
    <li><a href="/notes"><i class="fa-regular fa-notebook"></i> <span>Notes</span></a></li>
    <li><a href="/wishlist"><i class="fa-regular fa-book-heart"></i> <span>Wishlist</span></a></li>
    <li><a href="/contacts"><i class="fa-regular fa-spiral"></i> <span>Habits</span></a></li>
    <li><a href="/contacts"><i class="fa-regular fa-address-book"></i> <span>Contacts</span></a></li>
  </ul>
</nav>

<script>
  (() => {
    const nav = document.currentScript.previousElementSibling;

    if (sessionStorage.getItem("@ui/nav/expanded")) {
      nav.classList.add("expanded");

      // This waits two animation frames before enabling animations on the page,
      // effectively blocking the sidebar animation on page navigations.
      document.documentElement.classList.add("no-animation");
      requestAnimationFrame(() => requestAnimationFrame(() =>
        document.documentElement.classList.remove("no-animation")));

      // If the cursor already left during the load, collapse on the first move.
      document.addEventListener("mousemove", (e) => {
        if (!nav.contains(e.target)) {
          nav.classList.remove("expanded");
          sessionStorage.removeItem("@ui/nav/expanded");
        }
      }, { once: true });
    }

    nav.addEventListener("mouseenter", () => {
      nav.classList.add("expanded");
      sessionStorage.setItem("@ui/nav/expanded", "1");
    });

    nav.addEventListener("mouseleave", () => {
      nav.classList.remove("expanded");
      sessionStorage.removeItem("@ui/nav/expanded");
    });
  })();
</script>
