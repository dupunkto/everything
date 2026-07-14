<?php
  // Edit target for note textarea.

  $kind = @$_GET['kind'] ?? "person";
  $id = @$_GET['id'] ?? @$_POST['id'];

  if(!in_array($kind, ['person', 'org'])) 
    fail("Malformed 'kind' parameter.", status: 400);

  if($id && isset($_POST['note'])) {
    if($kind == "org") \store\update_organisation_note($id, $_POST['note'])
      or fail("Could not save note.");

    if($kind == "person") \store\update_contact_note($id, $_POST['note'])
      or fail("Could not save note.");
  }

  http_response_code(204); exit;
