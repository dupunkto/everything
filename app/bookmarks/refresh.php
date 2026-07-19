<?php

  $bookmark = \store\get_bookmark(@$_GET['id'])
    or fail("Bookmark not found.", status: 404);

  $favicon = \bookmarks\fetch_meta($bookmark['url'])['favicon'];
  \store\update_bookmark_favicon($bookmark['id'], $favicon)
    or fail("Could not refresh favicon.");

  http_response_code(303);
  header("Location: /bookmarks/edit?id=" . urlencode($bookmark['id'])); exit;
