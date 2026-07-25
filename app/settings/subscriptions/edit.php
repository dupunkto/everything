<?php

  if(isset($_POST["id"], $_POST["color"], $_POST["title"], $_POST["url"])) {
    $subscription = \store\get_subscription($_POST['id']);
    $fields = \core\diff($subscription,
      title: cast_string($_POST['title']), subtitle: cast_string($_POST['subtitle']),
      url: cast_string($_POST['url']), color: cast_color($_POST['color']),
      filter: cast_string(@$_POST['filter']));

    \store\update_subscription(
      $_POST['id'], cast_string($_POST['title']), cast_string($_POST['subtitle']),
      cast_string($_POST['url']), cast_color($_POST['color']), cast_string(@$_POST['filter']))
      or fail("Could not save subscription #" . $_POST['id'] . ".");

    \store\put_audit_log('subscriptions', $_POST['id'], "Updated [" . join(", ", $fields) . "] for subscriptions/{$_POST['id']}.", 'user')
      or fail("Could not create audit entry.");

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
