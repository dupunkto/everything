<?php

  \store\delete_task($_GET['id']) or fail("Could not delete task.");

  http_response_code(303);
  header("Location: /todo"); exit;
