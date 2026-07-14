<?php

  \store\delete_note($_GET['id']) or fail("Could not delete note.");

  http_response_code(303);
  header("Location: /notes"); exit;
