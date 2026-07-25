<?php

  $form = function() { ?>
    <form class="subscription-new-form" x-post="/settings/subscriptions/new" x-target="#subscription-new" x-replace="outerHTML" x-refresh="#subscriptions-listing">
      <input name="url" type="url" placeholder="iCalendar URL" required autofocus>
      <button type="submit">Find</button>
    </form>
  <?php };

  if(isset($_POST['url'])) {
    $response = \http\get($_POST['url']);

    if($response['state'] != 'success') {
      fail("Fetching {$_POST['url']} failed: network error", status: 502);
    }

    if($response['status'] >= 400) {
      fail("Fetching {$_POST['url']} failed: HTTP {$response['status']}", status: 502);
    }

    $feed = \icalendar\parse_feed($response['body'])
      or fail("Parsing {$_POST['url']} failed: not an iCalendar feed.", status: 400);

    $title = cast_string($feed['title'])
      ?? parse_url($_POST['url'], PHP_URL_HOST)
      ?? "Untitled subscription";

    $id = \store\put_subscription(
      cast_string($title),
      null,
      cast_string($_POST['url']),
      cast_color($feed['color'] ?? "#efefef")
    ) or fail("Could not create new subscription.");

    \store\put_log('subscriptions', $id, "Created subscription.", 'user')
      or fail("Could not create audit entry.");

    ?>
    <section id="subscription-new" z-dismiss="escape" hidden>
      <?php $form() ?>
    </section>
    <?php exit;
  }

  $form();
