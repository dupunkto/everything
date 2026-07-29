<?php

  $id = \store\put_tag("Untitled tag", cast_color("#efefef"), null);
  \store\put_audit_log('tags', $id, "Created tags/$id.", 'user', operation: 'insert');

  include __DIR__ . "/listing.php"; exit;
