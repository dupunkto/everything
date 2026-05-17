<?php
  \store\put_tag("Untitled tag", "#efefef", null) or fail("Could not create new tag.");
  include __DIR__ . "/listing.php"; exit;
?>