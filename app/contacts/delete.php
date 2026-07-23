<?php

  $kind = $_GET['kind'] ?? "person";

  if(!in_array($kind, ['person', 'org'])) 
    fail("Malformed 'kind' parameter.", status: 400);

  if($kind == "org")
    \store\delete_organisation($_GET['id']) or fail("Could not delete organisation.");

  if($kind == "person")
    \store\delete_contact($_GET['id']) or fail("Could not delete contact.");

  $table = $kind == 'org' ? 'organisations' : 'contacts';
  $label = $kind == 'org' ? "organisation" : "contact";
  \store\insert_log($table, $_GET['id'], "Deleted $label.", 'user')
    or fail("Could not create audit entry.");

  http_response_code(303);
  header("Location: /contacts"); exit;
