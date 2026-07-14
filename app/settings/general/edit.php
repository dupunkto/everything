<?php

  $providers = ['google_maps', 'apple_maps', 'openstreetmap', 'none'];

  if(isset($_POST['timezone'])) {
    if(!in_array($_POST['timezone'], \DateTimeZone::listIdentifiers()))
      fail("Invalid 'timezone' parameter.", status: 400);

    \store\update_config('timezone', $_POST['timezone'])
      or fail("Could not update timezone.");
  }

  if(isset($_POST['map-provider'])) {
    if(!in_array($_POST['map-provider'], $providers))
      fail("Invalid 'map-provider' parameter.", status: 400);

    \store\update_config('map-provider', $_POST['map-provider'])
      or fail("Could not update map provider.");
  }

  $timezones = [];

  foreach(\DateTimeZone::listIdentifiers() as $timezone) {
    $timezones[$timezone] = str_replace("_", " ", $timezone);
  }
?>
<form class="settings-form" x-post="/settings/general/edit" x-on="change" x-target="#general-settings">
  <label>
    Timezone
    <?php \forms\options('timezone', $timezones, \config\value('timezone'), flat: true) ?>
  </label>
  <label>
    Map provider
    <?php \forms\options('map-provider', [
      'google_maps' => 'Google Maps',
      'apple_maps' => 'Apple Maps',
      'openstreetmap' => 'OpenStreetMap',
      'none' => 'None',
    ], \config\value('map-provider'), flat: true) ?>
  </label>
</form>
