<?php

  if($method != 'DELETE') fail("Method not allowed.", status: 405);

  $note = \store\get_note($_GET['id']) or fail("Note not found.", status: 404);

  if($note['apple_id'])
    \store\put_note_tombstone($note['apple_id'], gmdate('Y-m-d H:i:s'));

  \store\delete_note($_GET['id']);
  \store\put_audit_log('notes', $_GET['id'], "Deleted notes/{$_GET['id']}.", 'user', operation: 'delete');

  see_other("/notes");
