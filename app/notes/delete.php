<?php

  \store\delete_note($_GET['id']);
  \store\put_audit_log('notes', $_GET['id'], "Deleted notes/{$_GET['id']}.", 'user', operation: 'delete');

  http_response_code(303);
  header("Location: /notes"); exit;
