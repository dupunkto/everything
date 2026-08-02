<?php

  $id = \store\put_share("Untitled share", bin2hex(random_bytes(32)));
  \store\put_audit_log('shares', $id, "Created shares/$id.", 'user', operation: 'insert');

  include __DIR__ . "/listing.php"; exit;
