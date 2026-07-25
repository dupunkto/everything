<?php

  \store\transaction(function() use ($id) {
    \store\delete_wish($_GET['id']) or fail("Could not delete wish.");
    \store\put_log('wishes', $_GET['id'], "Deleted wish.", 'user', operation: 'delete')
      or fail("Could not create audit entry.");

    \caldav\mark_resource_deleted('wish', $_GET['id']);
  });

  http_response_code(303);
  header("Location: /wishlist"); exit;
