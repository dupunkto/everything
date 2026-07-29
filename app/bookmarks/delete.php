<?php

  \store\delete_bookmark($_GET['id']);
  \store\put_audit_log('bookmarks', $_GET['id'], "Deleted bookmarks/{$_GET['id']}.", 'user', operation: 'delete');

  http_response_code(303);
  header("Location: /bookmarks"); exit;
