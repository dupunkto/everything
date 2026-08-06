<?php

$connector = \store\get_connector($_GET['id'])
  or fail("Connector not found.", status: 404);

\store\delete_connector($connector['id']);
\store\put_audit_log('connectors', $connector['id'], "Deleted connectors/{$connector['id']}.", 'user', operation: 'delete');

include __DIR__ . "/listing.php"; exit;
