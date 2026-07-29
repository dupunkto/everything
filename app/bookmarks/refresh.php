<?php

  $bookmark = \store\get_bookmark(@$_GET['id'])
    or fail("Bookmark not found.", status: 404);

  $favicon = \bookmarks\fetch_meta($bookmark['url'])['favicon'];
  \store\update_bookmark_favicon($bookmark['id'], $favicon);
  \store\put_audit_log('bookmarks', $bookmark['id'], "Refreshed favicon for bookmarks/{$bookmark['id']}.", 'system');

  http_response_code(303);
  header("Location: /bookmarks/edit?id=" . urlencode($bookmark['id'])); exit;
