<?php

  if($method != 'DELETE') fail("Method not allowed.", status: 405);

  \store\delete_note($_GET['id']);
  \store\put_audit_log('notes', $_GET['id'], "Deleted notes/{$_GET['id']}.", 'user', operation: 'delete');

  see_other("/notes");
