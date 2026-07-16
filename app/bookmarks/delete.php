<?php

  \store\delete_bookmark($_GET['id']) or fail("Could not delete bookmark.");

  http_response_code(303);
  header("Location: /bookmarks"); exit;
