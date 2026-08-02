<?php

$subscription = \store\get_subscription($_GET['id'])
  or fail("Subscription not found.", status: 404);

$retain = !cast_bool($subscription['history']);

\store\update_subscription_history($subscription['id'], $retain);
\store\put_audit_log('subscriptions', $subscription['id'],
  ($retain ? "Enabled" : "Disabled") . " history retention for subscriptions/{$subscription['id']}.", 'user');

include __DIR__ . "/listing.php"; exit;
