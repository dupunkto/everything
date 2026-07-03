<?php

  if(isset($_POST['url'])) {
    $probe = \store\probe_ical_feed($_POST['url'])
      or fail("Could not read iCal feed from " . $_POST['url'] . ".", status: 400);

    \store\create_subscription($probe['title'], null, $_POST['url'], $probe['color'] ?? "#efefef")
      or fail("Could not create new subscription.");

    include __DIR__ . "/listing.php"; exit;
  }

?>
<form class="subscription-new-form" x-post="/settings/subscriptions/new" x-target="#subscriptions-listing">
  <input name="url" type="url" placeholder="iCalendar URL" required autofocus>
  <button type="submit">Find</button>
</form>