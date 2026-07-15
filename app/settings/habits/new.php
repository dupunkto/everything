<?php

  \store\create_habit("Untitled habit", "1", cast_color("#efefef"), "fa-circle-check") or fail("Could not create new habit.");
  include __DIR__ . "/listing.php"; exit;
