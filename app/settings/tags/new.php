<?php

  \store\create_tag("Untitled tag", cast_color("#efefef"), null) or fail("Could not create new tag.");
  include __DIR__ . "/listing.php"; exit;
