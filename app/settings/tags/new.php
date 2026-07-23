<?php

  $id = \store\create_tag("Untitled tag", cast_color("#efefef"), null) or fail("Could not create new tag.");
  \store\insert_log('tags', $id, "Created tag.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
