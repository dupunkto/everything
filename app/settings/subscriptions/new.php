<?php

  if(isset($_POST['url'])) {
    $feed = \ical\fetch_feed($_POST['url'])
      or fail("Could not read iCal feed from " . $_POST['url'] . ".", status: 400);

    $title = $feed['title']
      ?? parse_url($_POST['url'], PHP_URL_HOST)
      ?? "Untitled subscription";

    \store\create_subscription($title, null, $_POST['url'], $feed['color'] ?? "#efefef")
      or fail("Could not create new subscription.");

    include __DIR__ . "/listing.php"; exit;
  }

?>
<form class="subscription-new-form" x-post="/settings/subscriptions/new" x-target="#subscriptions-listing">
  <input name="url" type="url" placeholder="iCalendar URL" required autofocus>
  <button type="submit">Find</button>
</form>
