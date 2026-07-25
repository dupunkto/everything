<?php

  \store\delete_note($_GET['id']) or fail("Could not delete note.");
  \store\put_audit_log('notes', $_GET['id'], "Deleted notes/{$_GET['id']}.", 'user', operation: 'delete')
    or fail("Could not create audit entry.");

  http_response_code(303);
  header("Location: /notes"); exit;
