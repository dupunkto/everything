<?php

  $sources = array_map('cast_string', $_POST['ids'] ?? []);
  $sources = array_values(array_filter($sources));

  \store\reorder_sources($sources);
  \store\put_audit_log('sources', '*', "Updated [position] for sources/*.", 'user');

  http_response_code(204);
