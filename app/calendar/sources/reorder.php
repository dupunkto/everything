<?php

  $sources = array_map('cast_string', $_POST['ids'] ?? []);
  $sources = array_values(array_filter($sources));

  \store\reorder_sources($sources) or fail("Could not reorder sources.");

  http_response_code(204);
