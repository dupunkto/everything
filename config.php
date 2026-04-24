<?php
// SQL-based configuration.

namespace config;

$_DEFAULTS = [];
$_CONFIG = \store\config();

foreach($_CONFIG as $key => $value) {
  define(normalize_key($key), normalize_value($value));
}

required('host');

fallback('force-https', false);
fallback('prefered-proto', FORCE_HTTPS ? "https" : "http");
fallback('secure', PREFERED_PROTO == "https");
fallback('canonical', PREFERED_PROTO . "://" . HOST);

function required($key) {
  if(!defined(normalize_key($key))) {
    die("Missing required key '$key' in config.");
  }
}

function fallback($key, $value) {
  global $_DEFAULTS;
  $key = normalize_key($key);
  
  if(!defined($key)) {
    $_DEFAULTS[$key] = $value;
    define($key, $value);
  }
}

function resolute($key, $value) {
  $key = normalize_key($key);
  
  if(defined($key)) {
    die("Resolute key '$key' cannot be overridden.");
  } else {
    define($key, $value);
  }
}

function optional($key) {
  // Do nothing, this is just here to document
  // what configuration is available.
}

function is_set($key) {
  global $_CONFIG;
  $key = normalize_key($key);
  return isset($_CONFIG[$key]);
}

function is_fallback($key) {
  global $_DEFAULTS;
  $key = normalize_key($key);
  return isset($_DEFAULTS[$key]);
}

function normalize_key($key) {
  $key = str_replace("-", "_", $key);
  $key = str_replace(".", "_", $key);

  return strtoupper($key);
}

function normalize_value($value) {
  return match($value) {
    "true", "on", "yes" => true,
    "false", "off", "no" => false,
    default => $value
  };
}
