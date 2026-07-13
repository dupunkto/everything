<?php

  $fields = [];
  foreach(['label', 'street_name', 'street_number', 'postal_code', 'city', 'province', 'country', 'timezone', 'note'] as $col)
    $fields[$col] = trim(@$_POST["addr_$col"] ?? "");

  if($fields['street_name'] !== "") \store\create_address($fields);

  include __DIR__ . "/listing.php";
