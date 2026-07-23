<?php

  \store\delete_wish($_GET['id']) or fail("Could not delete wish.");
  \store\insert_log('wishes', $_GET['id'], "Deleted wish.", 'user')
    or fail("Could not create audit entry.");

  http_response_code(303);
  header("Location: /wishlist"); exit;
