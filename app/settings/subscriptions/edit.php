<?php

  if(isset($_POST["id"], $_POST["color"], $_POST["title"], $_POST["url"])) {
    $subscription = \store\get_subscription($_POST['id']) or fail("Subscription not found.", status: 404);
    $fields = \core\diff($subscription,
      title: cast_str($_POST['title']), subtitle: cast_str($_POST['subtitle']),
      url: cast_str($_POST['url']), color: cast_color($_POST['color']),
      filter: cast_str(@$_POST['filter']));

    \store\update_subscription(
      $_POST['id'], cast_str($_POST['title']), cast_str($_POST['subtitle']),
      cast_str($_POST['url']), cast_color($_POST['color']), cast_str(@$_POST['filter']));

    \store\put_audit_log('subscriptions', $_POST['id'], "Updated [" . join(", ", $fields) . "] for subscriptions/{$_POST['id']}.", 'user');

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
