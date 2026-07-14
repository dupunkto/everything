<?php
  // Blank contact edit form.

  $q = $_GET['q'] ?? $_POST['q'] ?? "";
  $is_org = str_contains($q, "is:org") && !str_contains($q, "is:person");
  $_GET['kind'] = $is_org ? "org" : "person";

  unset($_GET['id'], $_POST['id']);

  include __DIR__ . "/edit.php";
