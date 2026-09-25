<?php

  if($method != 'POST') fail("Method not allowed.", status: 405);

  $sources = array_values(array_filter(array_map('cast_str', $_POST['ids'] ?? [])));

  \store\reorder_sources($sources);
  \store\put_audit_log('sources', '*', "Updated [position] for sources/*.", 'user');

  stay_on_page();
