<?php
// Trigger an iCal sync. With ?id=... syncs that subscription; without,
// syncs all of them. Idempotent — safe to hammer.

header('Content-Type: text/plain');

if(isset($_GET['id']) && $_GET['id'] !== '') {
  $id = $_GET['id'];
  $result = \store\sync_subscription($id);
  echo "$id: " . json_encode($result) . "\n";
  exit;
}

$results = \store\sync_all_subscriptions();
if($results === []) {
  echo "No subscriptions to sync.\n";
  exit;
}

foreach($results as $id => $stats) {
  echo "$id: " . json_encode($stats) . "\n";
}
