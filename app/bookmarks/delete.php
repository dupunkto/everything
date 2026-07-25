<?php

  \store\delete_bookmark($_GET['id']) or fail("Could not delete bookmark.");
  \store\put_log('bookmarks', $_GET['id'], "Deleted bookmark.", 'user', operation: 'delete')
    or fail("Could not create audit entry.");

  http_response_code(303);
  header("Location: /bookmarks"); exit;
