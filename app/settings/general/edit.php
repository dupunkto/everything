<?php

  // These would be loaded in the store, but since neuro is not yet available when
  // the store loads, we define them here instead, since this is the only callsite at this point.
  define('ENUM_CURRENCY', array_keys(CURRENCY_SYMBOLS));
  define('ENUM_MAP_PROVIDER', array_keys(map_provider_options()));

  if(isset($_POST['timezone'])) {
    if(!in_array($_POST['timezone'], \DateTimeZone::listIdentifiers()))
      fail("Invalid 'timezone' parameter.", status: 400);

    \store\update_config('timezone', $_POST['timezone'])
      or fail("Could not update timezone.");
  }

  if(isset($_POST['map-provider'])) {
    if(!in_array($_POST['map-provider'], ENUM_MAP_PROVIDER))
      fail("Invalid 'map-provider' parameter.", status: 400);

    \store\update_config('map-provider', $_POST['map-provider'])
      or fail("Could not update map provider.");
  }

  if(isset($_POST['currency'])) {
    if(!in_array($_POST['currency'], ENUM_CURRENCY))
      fail("Invalid 'currency' parameter.", status: 400);

    \store\update_config('currency', $_POST['currency'])
      or fail("Could not update currency.");
  }

  if($_POST) {
    \store\put_log('config', 'general', "Updated general settings.", 'user')
      or fail("Could not create audit entry.");
  }

  $timezones = [];

  foreach(\DateTimeZone::listIdentifiers() as $timezone) {
    $timezones[$timezone] = str_replace("_", " ", $timezone);
  }

  $currency_options = array_combine(ENUM_CURRENCY, array_map(fn($c) =>
    strtoupper($c) . " (" . CURRENCY_SYMBOLS[$c] . ")", ENUM_CURRENCY));

?>
<form class="settings-form" x-post="/settings/general/edit" x-on="change" x-target="#general-settings">
  <label>
    Timezone
    <?php \forms\options('timezone', $timezones, \config\fresh_value('timezone'), flat: true) ?>
  </label>
  <label>
    Map provider
    <?php \forms\options('map-provider', map_provider_options(), \config\fresh_value('map-provider'), flat: true) ?>
  </label>
  <label>
    Currency
    <?php \forms\options('currency', $currency_options, \config\fresh_value('currency'), flat: true) ?>
  </label>
</form>
