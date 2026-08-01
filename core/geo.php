<?php
// Address and map helpers.

function address_line($a) {
  $parts = [
    @$a['street_address'] ?: '',
    trim((@$a['postal_code'] ?: '') . " " . (@$a['city'] ?: '')),
    @$a['province'] ?: '',
    @$a['country'] ?: '',
  ];

  return str_implode(", ", $parts);
}

function country_codes() {
  return explode(" ", "AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW");
}

function country_options() {
  $countries = country_codes();
  return array_combine($countries, $countries);
}

function timezone_options() {
  return array_combine(ENUM_TIMEZONE, array_map(fn($tz) =>
    str_replace("_", " ", $tz), ENUM_TIMEZONE));
}

function map_provider_options() {
  return [
    'google_maps' => 'Google Maps',
    'apple_maps' => 'Apple Maps',
    'openstreetmap' => 'OpenStreetMap',
    'none' => 'None',
  ];
}

function map_provider_label($provider = MAP_PROVIDER) {
  return map_provider_options()[$provider] ?? $provider;
}

function maps_url($destination) {
  $to = rawurlencode($destination);

  return match(MAP_PROVIDER) {
    'google_maps' => "https://www.google.com/maps/dir/?api=1&destination=$to",
    'apple_maps' => "https://maps.apple.com/?daddr=$to",
    'openstreetmap' => "https://www.openstreetmap.org/directions?to=$to",
    default => null,
  };
}
