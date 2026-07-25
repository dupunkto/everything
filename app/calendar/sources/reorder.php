<?php

  $sources = array_map('cast_string', $_POST['ids'] ?? []);
  $sources = array_values(array_filter($sources));

  \store\reorder_sources($sources) or fail("Could not reorder sources.");
  \store\put_log('sources', '*', "Reordered calendar sources.", 'user')
    or fail("Could not create audit entry.");

  http_response_code(204);
