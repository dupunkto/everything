<?php
$segments = array_values(array_filter(explode("/", $path)));
$section = @$segments[0];
$actions = $section ? path_join(__DIR__, "..", $section, "actions.php") : null;
?>

<nav class="nav">
  <div class="nav__actions">
    <?php if($actions && file_exists($actions)) include $actions ?>
  </div>

  <ul>
    <li><a z-key="1" href="/mail"><i class="fa-regular fa-inbox"></i> <span>Mail</span></a></li>
    <li><a z-key="2" href="/calendar"><i class="fa-regular fa-calendar"></i> <span>Calendar</span></a></li>
    <li><a z-key="3" href="/todo"><i class="fa-regular fa-box-check"></i> <span>ToDo</span></a></li>
    <li><a z-key="4" href="/tracker"><i class="fa-regular fa-timer"></i> <span>Tracker</span></a></li>
    <li><a z-key="5" href="/notes"><i class="fa-regular fa-notebook"></i> <span>Notes</span></a></li>
    <li><a z-key="6" href="/bookmarks"><i class="fa-regular fa-bookmark"></i> <span>Bookmarks</span></a></li>
    <li><a z-key="7" href="/wishlist"><i class="fa-regular fa-book-heart"></i> <span>Wishlist</span></a></li>
    <li><a z-key="8" href="/contacts"><i class="fa-regular fa-address-book"></i> <span>Contacts</span></a></li>
    <li><a z-key="9" href="/addresses"><i class="fa-regular fa-location-arrow"></i> <span>Addresses</span></a></li>
    <li hidden><a z-key="?" href="/shortcuts">Shortcuts</a></li>
    <li><a z-key="0" href="/settings"><i class="fa-regular fa-gear"></i> <span>Settings</span></a></li>
  </ul>

  <button type="button" class="nav__toggle" title="Toggle sidebar">
    <i class="fa-solid fa-chevron-left"></i>
  </button>
</nav>

<button type="button" z-key="mod+/ mod+k" z-toggle="#global-search" data-global-search-toggle hidden></button>
<div id="global-search" class="global-search" role="dialog" aria-modal="true" aria-label="Global search" z-dismiss="escape" hidden>
  <button type="button" class="global-search__backdrop" z-toggle="#global-search" aria-label="Close search"></button>
  <section class="global-search__content" z-nav=".global-search__input, .global-search__item">
    <form id="global-search-form" class="global-search__bar">
      <input
        class="global-search__input"
        name="q"
        type="search"
        placeholder="Search everything…"
        aria-label="Search everything"
        x-get="/search/listing"
        x-on="input"
        x-target="#global-search-results"
        x-data="#global-search-form"
      >
    </form>
    <section class="global-search__panel">
      <section id="global-search-results" aria-live="polite"></section>
    </section>
  </section>
</div>
