<?php

  \store\delete_subscription($_GET['id'])
    or fail("Could not delete subscription #" . $_GET['id']);
  \store\insert_log('subscriptions', $_GET['id'], "Deleted subscription.", 'user')
    or fail("Could not create audit entry.");

  include __DIR__ . "/listing.php"; exit;
