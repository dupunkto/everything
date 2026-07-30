<?php

  if($method != 'POST') fail("Method not allowed.", status: 405);

  $sources = array_map('cast_string', $_POST['ids'] ?? []);
  $sources = array_values(array_filter($sources));

  \store\reorder_sources($sources);
  \store\put_audit_log('sources', '*', "Updated [position] for sources/*.", 'user');

  stay_on_page();
