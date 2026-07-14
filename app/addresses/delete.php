<?php

  $id = @$_GET['id'] or fail("Address is missing.", status: 400);
  \store\get_address($id) or fail("Address not found.", status: 404);
  \store\delete_address($id) or fail("Could not delete address.");

  include __DIR__ . "/listing.php"; exit;
