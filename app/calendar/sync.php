<?php
// Triggers an idempotent iCalendar sync. Optionally takes a specific
// subscription to sync, otherwise syncs everything.

header("Content-Type: text/plain");

$ids = empty($_GET['id'])
  ? array_column(\store\list_subscriptions() ?: [], 'id')
  : [$_GET['id']];

if(!$ids) {
  echo "No subscriptions to sync.\n";
  exit;
}

foreach($ids as $id)
  echo "$id: " . json_encode(\sync\subscription($id)) . "\n";
