<?php

  if($method != 'DELETE') fail("Method not allowed.", status: 405);

  \store\delete_timing($_GET['id']);
  \store\put_audit_log('timings', $_GET['id'], "Deleted timings/{$_GET['id']}.", 'user', operation: 'delete');

  stay_on_page();
