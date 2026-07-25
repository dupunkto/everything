<?php

  \store\delete_quota($_GET['tag_id']) or fail("Could not delete tracker quota.");
  \store\put_log('quotas', $_GET['tag_id'], "Deleted tracker quota.", 'user', operation: 'delete')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
