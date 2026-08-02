<?php

  $id = cast_str(@$_GET['id']);

  [$page, $offset] = $id
    ? \store\resolve_timing_page($id, LISTING_PAGE_SIZE)
    : listing_page();

  [$timings, $has_more] = listing_batch(
    \store\list_timings_paginated(LISTING_PAGE_SIZE + 1, offset: $offset)
  );

  $timing_tags = group_by(
    \store\list_timings_tags(array_column($timings, 'id')),
    'timing'
  );

  if($page > 1 && !$id) {
    include __DIR__ . "/items.php"; return;
  }

?>
<ul class="tracker-list<?= $id ? ' tracker-list--focused' : '' ?>">
  <?php include __DIR__ . "/items.php" ?>
</ul>
