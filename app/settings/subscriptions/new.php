<?php

  if(isset($_POST['url'])) {
    $url = cast_string($_POST['url']);
    $feed = \ical\fetch_feed($url)
      or fail("Could not read iCal feed from " . $url . ".", status: 400);

    $title = cast_string($feed['title'] ?? parse_url($url, PHP_URL_HOST) ?? "Untitled subscription");

    \store\create_subscription($title, null, $url, cast_color($feed['color'] ?? "#efefef"))
      or fail("Could not create new subscription.");

    include __DIR__ . "/listing.php"; exit;
  }

?>
<form class="subscription-new-form" x-post="/settings/subscriptions/new" x-target="#subscriptions-listing">
  <input name="url" type="url" placeholder="iCalendar URL" required autofocus>
  <button type="submit">Find</button>
</form>
