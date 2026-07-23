<?php

  \store\delete_tag($_GET['id']) or fail("Could not delete tag #" . $_GET['id']);
  \store\insert_log('tags', $_GET['id'], "Deleted tag.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
