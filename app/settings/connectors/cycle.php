<?php

$connector = \store\get_connector($_GET['id'])
  or fail("Connector not found.", status: 404);

\store\update_connector_token($connector['id'], bin2hex(random_bytes(32)));
\store\put_audit_log('connectors', $connector['id'], "Cycled token for connectors/{$connector['id']}.", 'user');

include __DIR__ . "/listing.php"; exit;
