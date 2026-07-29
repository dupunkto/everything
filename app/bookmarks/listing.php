<?php

  $query = @$_GET['q'] ?: @$_POST['q'] ?: "";
  [$page, $offset] = listing_page();

  [$bookmarks, $has_more] = listing_batch(
    \store\list_bookmarks_paginated($query, LISTING_PAGE_SIZE + 1, offset: $offset)
  );

  if($page > 1) {
    include __DIR__ . "/items.php"; return;
  }

?>
<ul class="listing">
  <?php include __DIR__ . "/items.php" ?>
</ul>
