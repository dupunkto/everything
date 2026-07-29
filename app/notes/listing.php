<?php

  $query = @$_GET['q'] ?: @$_POST['q'] ?: "";
  [$page, $offset] = listing_page();
  [$notes, $has_more] = listing_batch(
    \store\list_notes_paginated($query, LISTING_PAGE_SIZE + 1, offset: $offset)
  );

  if($page > 1) {
    include __DIR__ . "/items.php"; return;
  }

?>
<ul class="<?= NOTES_LAYOUT == 'masonry' ? 'notes-grid' : 'listing' ?>">
  <?php include __DIR__ . "/items.php" ?>
</ul>
