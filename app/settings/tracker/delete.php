<?php

  \store\delete_quota($_GET['tag_id']) or fail("Could not delete tracker quota.");

  include __DIR__ . "/listing.php"; exit;
