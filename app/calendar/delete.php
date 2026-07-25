<?php

  $appointment = \store\get_appointment($_POST['id'])
    or fail("Appointment not found.", status: 404);

  if(!empty($appointment['subscription_id']))
    fail("This appointment can't be deleted.", status: 403);

  \store\transaction(function() use ($appointment) {
    \store\delete_appointment($appointment['id'])
      or fail("Could not delete appointment.");
    \store\put_audit_log('appointments', $appointment['id'], "Deleted appointments/{$appointment['id']}.", 'user', operation: 'delete')
      or fail("Could not create audit entry.");

    \caldav\mark_resource_deleted('appointment', $appointment['id']);
  });

  // The caller re-fetches the week itself, nothing to render here.
  http_response_code(204); exit;
