<?php

  $id = \store\put_habit("Untitled habit", "1", cast_color("#efefef"), "fa-circle-check") or fail("Could not create new habit.");
  \store\put_audit_log('habits', $id, "Created habits/$id.", 'user', operation: 'insert')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
