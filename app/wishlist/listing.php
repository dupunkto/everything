<?php

  $show = @$_GET['show'] ?: @$_POST['show'] ?: 'dream';
  $include = @$_GET['i'] ?: @$_POST['i'];

  // This array includes IDs of items that have just been clicked.
  // We do not want to have them disappear from under the users cursor,
  // that is a very bad UX. So this 'skips' them from the query that
  // is currently active.
  $include = $include ? explode(",", $include) : [];

  $statuses = match($show) {
    'shelves' => ['nvm'],
    'bought'  => ['dream', 'bought'],
    default   => ['dream'],
  };

  [$page, $offset] = listing_page();

  [$wishes, $has_more] = listing_batch(\store\list_wishes_paginated(
    $statuses,
    $include,
    LISTING_PAGE_SIZE + 1,
    offset: $offset
  ));

  if($page > 1) {
    include __DIR__ . "/items.php"; return;
  }

?>
<div class="page-header">
  <h1 class="page-header__title"><strong><?= $show == 'shelves' ? 'Shelves' : 'Wishlist' ?></strong></h1>

  <nav class="view-nav">
    <?php if($show == 'shelves'): ?>
      <button type="button" z-set="#wishlist-filter" value="dream">&larr; Back to wishlist</button>
    <?php elseif($show == 'bought'): ?>
      <button type="button" z-set="#wishlist-filter" value="dream"><i class="fa-regular fa-eye"></i> Hide bought</button>
      <button type="button" z-set="#wishlist-filter" value="shelves"><i class="fa-regular fa-box-archive"></i> Shelves</button>
    <?php else: ?>
      <button type="button" z-set="#wishlist-filter" value="bought"><i class="fa-regular fa-eye-slash"></i> Show bought</button>
      <button type="button" z-set="#wishlist-filter" value="shelves"><i class="fa-regular fa-box-archive"></i> Shelves</button>
    <?php endif ?>
  </nav>
</div>

<ul class="listing">
  <?php include __DIR__ . "/items.php" ?>
</ul>
