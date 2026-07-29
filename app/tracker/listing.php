<?php

  [$page, $offset] = listing_page();

  [$timings, $has_more] = listing_batch(
    \store\list_timings_paginated(LISTING_PAGE_SIZE + 1, offset: $offset)
  );

  if($page > 1) {
    include __DIR__ . "/items.php"; return;
  }

?>
<ul class="tracker-list">
  <?php include __DIR__ . "/items.php" ?>
</ul>
