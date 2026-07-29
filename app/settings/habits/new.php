<?php

  $id = \store\put_habit("Untitled habit", "1", cast_color("#efefef"), "fa-circle-check");
  \store\put_audit_log('habits', $id, "Created habits/$id.", 'user', operation: 'insert');

  include __DIR__ . "/listing.php"; exit;
