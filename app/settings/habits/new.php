<?php

  $id = \store\create_habit("Untitled habit", "1", cast_color("#efefef"), "fa-circle-check") or fail("Could not create new habit.");
  \store\insert_log('habits', $id, "Created habit.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
