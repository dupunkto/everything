<?php

  \store\delete_address($_GET['id']) or fail("Could not delete address.");

  include __DIR__ . "/listing.php"; exit;
