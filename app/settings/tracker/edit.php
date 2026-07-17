<?php

  \store\update_quota(
    cast_int($_POST['tag_id']),
    cast_string($_POST['period']),
    cast_int($_POST['hours']),
    cast_int($_POST['minutes']),
    cast_date($_POST['start_date'])
  ) or fail("Could not save tracker quota.", status: 400);

  include __DIR__ . "/listing.php"; exit;
