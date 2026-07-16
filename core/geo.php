<?php
// Address and map helpers.

function address_line($a) {
  $parts = [
    trim((@$a['street_name'] ?: '') . " " . (@$a['street_number'] ?: '')),
    trim((@$a['postal_code'] ?: '') . " " . (@$a['city'] ?: '')),
    @$a['province'] ?: '',
    @$a['country'] ?: '',
  ];

  return str_implode(", ", $parts);
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
