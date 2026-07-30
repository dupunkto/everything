<?php

  if($method != 'DELETE') fail("Method not allowed.", status: 405);

  \store\delete_bookmark($_GET['id']);
  \store\put_audit_log('bookmarks', $_GET['id'], "Deleted bookmarks/{$_GET['id']}.", 'user', operation: 'delete');

  see_other("/bookmarks");
