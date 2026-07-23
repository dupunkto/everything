<?php

  $appointment = \store\get_appointment($_POST['id'])
    or fail("Appointment not found.", status: 404);

  if(!empty($appointment['subscription_id']))
    fail("This appointment can't be deleted.", status: 403);

  \store\delete_appointment($appointment['id'])
    or fail("Could not delete appointment.");
  \store\insert_log('appointments', $appointment['id'], "Deleted appointment.", 'user')
    or fail("Could not create audit entry.");

  // The caller re-fetches the week itself, nothing to render here.
  http_response_code(204); exit;
