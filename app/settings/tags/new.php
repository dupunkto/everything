<?php

  $id = \store\put_tag("Untitled tag", cast_color("#efefef"), null) or fail("Could not create new tag.");
  \store\put_audit_log('tags', $id, "Created tags/$id.", 'user', operation: 'insert')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
