<?php

  if(isset($_POST['addr_street_address'])) {
    if(is_str(@$_POST['addr_country']) && !in_array($_POST['addr_country'], country_codes()))
      fail("Invalid 'addr_country' parameter.", status: 400);

    // TODO(robin): correct pattern?
    // This route should probably be split into /new and /edit

    $fields = [
      'label' => cast_str($_POST['addr_label']),
      'street_address' => cast_str($_POST['addr_street_address']),
      'postal_code' => cast_str(@$_POST['addr_postal_code']),
      'city' => cast_str(@$_POST['addr_city']),
      'province' => cast_str($_POST['addr_province']),
      'country' => cast_str(@$_POST['addr_country']),
    ];

    if(isset($_POST['addr_id'])) {
      $address = \store\get_address($_POST['addr_id']) or fail("Address not found.", status: 404);

      \store\update_address($_POST['addr_id'], ...$fields);

      $changed = \core\diff($address, ...$fields);

      \store\put_audit_log('addresses', $_POST['addr_id'],
        "Updated [" . join(", ", $changed) . "] for addresses/{$_POST['addr_id']}.", 'user');
    }
    else {
      $id = \store\put_address(...$fields);
      \store\put_audit_log('addresses', $id, "Created addresses/$id.", 'user', operation: 'insert');
    }


  }

  include __DIR__ . "/listing.php";
