<?php

  \store\delete_timing($_GET['id']) or fail("Could not delete timing #" . $_GET['id']);
  \store\put_log('timings', $_GET['id'], "Deleted timing.", 'user', operation: 'delete')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
