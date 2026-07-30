<?php

  // These would be loaded in the store, but since neuro is not yet available when
  // the store loads, we define them here instead, since this is the only callsite at this point.
  define('ENUM_CURRENCY', array_keys(CURRENCY_SYMBOLS));
  define('ENUM_MAP_PROVIDER', array_keys(map_provider_options()));
  define('ENUM_PHONE_REGION', array_keys(phone_region_options()));
  define('ENUM_TIME_FORMAT', ['12-hour', '24-hour']);

  if(isset($_POST['phone-region'])) {
    if(!in_array($_POST['phone-region'], ENUM_PHONE_REGION))
      fail("Invalid 'phone-region' parameter.", status: 400);

    \store\update_config('phone-region', $_POST['phone-region']);
    \store\put_audit_log('config', 'general', "Set phone-region to '{$_POST['phone-region']}'.", 'user');
  }

  if(isset($_POST['timezone'])) {
    if(!in_array($_POST['timezone'], \DateTimeZone::listIdentifiers()))
      fail("Invalid 'timezone' parameter.", status: 400);

    \store\update_config('timezone', $_POST['timezone']);
    \store\put_audit_log('config', 'general', "Set timezone to '{$_POST['timezone']}'.", 'user');
  }

  if(isset($_POST['time-format'])) {
    if(!in_array($_POST['time-format'], ENUM_TIME_FORMAT))
      fail("Invalid 'time-format' parameter.", status: 400);

    \store\update_config('time-format', $_POST['time-format']);
    \store\put_audit_log('config', 'general', "Set time-format to '{$_POST['time-format']}'.", 'user');
  }

  if(isset($_POST['map-provider'])) {
    if(!in_array($_POST['map-provider'], ENUM_MAP_PROVIDER))
      fail("Invalid 'map-provider' parameter.", status: 400);

    \store\update_config('map-provider', $_POST['map-provider']);
    \store\put_audit_log('config', 'general', "Set map-provider to '{$_POST['map-provider']}'.", 'user');
  }

  if(isset($_POST['currency'])) {
    if(!in_array($_POST['currency'], ENUM_CURRENCY))
      fail("Invalid 'currency' parameter.", status: 400);

    \store\update_config('currency', $_POST['currency']);
    \store\put_audit_log('config', 'general', "Set currency to '{$_POST['currency']}'.", 'user');
  }

  function timezone_options() {
    $timezones = \DateTimeZone::listIdentifiers();
    return array_combine($timezones, array_map(fn($tz) =>
      str_replace("_", " ", $tz), $timezones));
  }

  function currency_options() {
    return array_combine(ENUM_CURRENCY, array_map(fn($c) =>
      strtoupper($c) . " (" . CURRENCY_SYMBOLS[$c] . ")", ENUM_CURRENCY));
  }

?>
<form class="settings-form" x-post="/settings/locales/edit" x-on="change" x-target="#locale-settings">
  <label>
    Timezone
    <?php \forms\options('timezone', timezone_options(), \config\fresh_value('timezone'), flat: true) ?>
  </label>
  <label>
    Time format
    <?php \forms\options('time-format', ENUM_TIME_FORMAT, \config\fresh_value('time-format')) ?>
  </label>
  <label>
    Phone region
    <?php \forms\options('phone-region', phone_region_options(), \config\fresh_value('phone-region'), flat: true) ?>
  </label>
  <label>
    Map provider
    <?php \forms\options('map-provider', map_provider_options(), \config\fresh_value('map-provider'), flat: true) ?>
  </label>
  <label>
    Currency
    <?php \forms\options('currency', currency_options(), \config\fresh_value('currency'), flat: true) ?>
  </label>
</form>
