<?php

  if(isset($_POST['addr_street_name'], $_POST['addr_street_number'], $_POST['addr_postal_code'], $_POST['addr_city'], $_POST['addr_province'], $_POST['addr_country'], $_POST['addr_timezone'])) {
    $fields = [
      'label' => cast_string($_POST['addr_label']),
      'street_name' => cast_string($_POST['addr_street_name']),
      'street_number' => cast_string($_POST['addr_street_number']),
      'postal_code' => cast_string($_POST['addr_postal_code']),
      'city' => cast_string($_POST['addr_city']),
      'province' => cast_string($_POST['addr_province']),
      'country' => cast_string($_POST['addr_country']),
      'timezone' => cast_string($_POST['addr_timezone']),
    ];

    if($_POST['addr_id']) {
      $address = \store\get_address($_POST['addr_id']);
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

    \store\put_audit_log('addresses', $id, $message, 'user', operation: $operation)
      or fail("Could not create audit entry.");
  }

  include __DIR__ . "/listing.php";
