<?php

  \store\delete_subscription($_GET['id'])
    or fail("Could not delete subscription #" . $_GET['id']);

  include __DIR__ . "/listing.php"; exit;
