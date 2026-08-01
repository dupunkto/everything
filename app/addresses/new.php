<?php

  if(isset($_POST['addr_street_address'], $_POST['addr_postal_code'], $_POST['addr_city'], $_POST['addr_country'])) {
    if(!in_array($_POST['addr_country'], country_codes()))
      fail("Invalid 'addr_country' parameter.", status: 400);

    $fields = [
      'label' => cast_string($_POST['addr_label']),
      'street_address' => cast_string($_POST['addr_street_address']),
      'postal_code' => cast_string($_POST['addr_postal_code']),
      'city' => cast_string($_POST['addr_city']),
      'province' => cast_string($_POST['addr_province']),
      'country' => cast_string($_POST['addr_country']),
    ];

    if($_POST['addr_id']) {
      $address = \store\get_address($_POST['addr_id']) or fail("Address not found.", status: 404);
      \store\update_address($_POST['addr_id'], ...$fields);
      $id = $_POST['addr_id'];
      $changed = \core\diff($address, ...$fields);
      $message = "Updated [" . join(", ", $changed) . "] for addresses/$id.";
      $operation = 'update';
    }
    else {
      $id = \store\put_address(...$fields);
      $message = "Created addresses/$id.";
      $operation = 'insert';
    }

    \store\put_audit_log('addresses', $id, $message, 'user', operation: $operation);
  }

  include __DIR__ . "/listing.php";
