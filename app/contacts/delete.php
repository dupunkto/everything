<?php

  if($method != 'DELETE') fail("Method not allowed.", status: 405);

  $kind = $_GET['kind'] ?? "person";

  if(!in_array($kind, ['person', 'org']))
    fail("Malformed 'kind' parameter.", status: 400);

  if($kind == "org")
    \store\delete_organisation($_GET['id']);

  if($kind == "person")
    \store\delete_contact($_GET['id']);

  $table = $kind == 'org' ? 'organisations' : 'contacts';
  \store\put_audit_log($table, $_GET['id'], "Deleted $table/{$_GET['id']}.", 'user', operation: 'delete');

  see_other("/contacts");
