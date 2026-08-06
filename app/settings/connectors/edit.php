<?php

$connector = \store\get_connector($_POST['id'])
  or fail("Connector not found.", status: 404);

$apps = array_values(array_unique((array)@$_POST['app']));
sort($apps);

$before = array_column(\store\list_connector_apps($connector['id']), 'app');

$fields = \core\diff($connector, name: $_POST['name']);
if($before != $apps) $fields[] = 'apps';

\store\update_connector($connector['id'], $_POST['name']);

\store\set_connector_apps($connector['id'], $apps);

if($fields) \store\put_audit_log('connectors', $connector['id'],
  "Updated [" . join(", ", $fields) . "] for connectors/{$connector['id']}.", 'user');

include __DIR__ . "/listing.php"; exit;
