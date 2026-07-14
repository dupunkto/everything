<?php

  \store\delete_wish($_GET['id']) or fail("Could not delete wish.");

  http_response_code(303);
  header("Location: /wishlist"); exit;
