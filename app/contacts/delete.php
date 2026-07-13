<?php

  $kind = @$_GET['kind'] ?? "person";
  $id = @$_GET['id'] or fail("Contact is missing.", status: 400);

  if(!in_array($kind, ['person', 'org'])) 
    fail("Malformed 'kind' parameter.", status: 400);

  if($kind == "org")
    \store\delete_organisation($id) or fail("Could not delete organisation.");

  if($kind == "person")
    \store\delete_contact($id) or fail("Could not delete contact.");

  http_response_code(303);
  header("Location: /contacts"); exit;
