<?php

  if($method != 'POST') fail("Method not allowed.", status: 405);

  $appointment = \store\get_appointment($_POST['id'])
    or fail("Appointment not found.", status: 404);

  if(!empty($appointment['subscription_id']))
    fail("This appointment can't be deleted.", status: 403);

  \store\delete_appointment($appointment['id']);
  \store\put_audit_log('appointments', $appointment['id'], "Deleted appointments/{$appointment['id']}.", 'user', operation: 'delete');

  \caldav\mark_resource_deleted('appointment', $appointment['id']);

  // The caller re-fetches the week itself, nothing to render here.
  stay_on_page();
