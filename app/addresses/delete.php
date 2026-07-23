<?php

  \store\delete_address($_GET['id']) or fail("Could not delete address.");
  \store\insert_log('addresses', $_GET['id'], "Deleted address.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
