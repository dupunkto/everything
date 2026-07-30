<?php

  if($method != 'DELETE') fail("Method not allowed.", status: 405);

  \store\delete_wish($_GET['id']);
  \store\put_audit_log('wishes', $_GET['id'], "Deleted wishes/{$_GET['id']}.", 'user', operation: 'delete');

  \caldav\mark_resource_deleted('wish', $_GET['id']);

  see_other("/wishlist");
