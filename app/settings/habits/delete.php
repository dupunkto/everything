<?php

  \store\delete_habit($_GET['id']) or fail("Could not delete habit #" . $id);
  \store\insert_log('habits', $_GET['id'], "Deleted habit.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
