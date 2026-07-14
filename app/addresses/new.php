<?php

  if(isset($_POST['addr_street_name'], $_POST['addr_street_number'], $_POST['addr_postal_code'], $_POST['addr_city'], $_POST['addr_province'], $_POST['addr_country'], $_POST['addr_timezone'])) {
    $fields = [
      'label' => cast_string(@$_POST['addr_label']),
      'street_name' => cast_string(@$_POST['addr_street_name']),
      'street_number' => cast_string(@$_POST['addr_street_number']),
      'postal_code' => cast_string(@$_POST['addr_postal_code']),
      'city' => cast_string(@$_POST['addr_city']),
      'province' => cast_string(@$_POST['addr_province']),
      'country' => cast_string(@$_POST['addr_country']),
      'timezone' => cast_string(@$_POST['addr_timezone']),
    ];

    if(@$_POST['addr_id']) {
      \store\update_address($_POST['addr_id'], ...$fields);
    }
    else {
      \store\create_address(...$fields);
    }
  }

  include __DIR__ . "/listing.php";
