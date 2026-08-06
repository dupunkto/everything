<?php

$id = \store\put_connector("Untitled connector", bin2hex(random_bytes(32)));

\store\put_audit_log('connectors', $id, "Created connectors/$id.", 'user', operation: 'insert');

include __DIR__ . "/listing.php"; exit;
