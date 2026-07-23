<?php
// Triggers an idempotent iCalendar sync. Optionally takes a specific
// subscription to sync, otherwise syncs everything.

$ids = empty($_GET['id'])
  ? array_column(\store\list_subscriptions() ?: [], 'id')
  : [$_GET['id']];

foreach($ids as $id) {
  $result = \subscription\sync($id);

  if(isset($result['error'])) {
    fail("Could not sync subscription #$id: " . $result['error']);
  }

  \store\insert_log('subscriptions', $id, "Synced subscription.", 'syncer')
    or fail("Could not create audit entry.");
}

include __DIR__ . "/week.php"; exit;
