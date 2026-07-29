<?php
// Pluggable authentication, supporting HTTP Basic auth and Nym.

$_AUTH_USER = getenv("AUTH_USER") ?: "everything";

$_AUTH_PROVIDER = getenv("AUTH_PROVIDER");
$_AUTH_PASSWORD = getenv("AUTH_PASSWORD");
$_AUTH_ENDPOINT = getenv("AUTH_ENDPOINT");

if(!$_AUTH_PROVIDER) {
  fail("AUTH_PROVIDER is not configured.");
}

if(!in_array($_AUTH_PROVIDER, ['basic', 'nym'])) {
  fail("Unknown authentication provider '$_AUTH_PROVIDER'.");
}

if($_AUTH_PROVIDER == 'nym' && !$_AUTH_ENDPOINT) {
  fail("The configured auth provider was set to 'nym', but no endpoint
      was configured. Either export AUTH_ENDPOINT in the environment, or
      set AUTH_PROVIDER to 'basic' instead.");
}

if($_AUTH_PROVIDER == 'basic' && !$_AUTH_PASSWORD) {
  fail("The configured auth provider was set to 'basic', but no password
      was configured. Either export AUTH_PASSWORD in the environment, or
      set AUTH_PROVIDER to 'nym' instead.");
}

if($_AUTH_PROVIDER == 'nym') {
  // TODO(robin): implement nym.

  fail("Nym has not yet been implemented. Blame Robin being lazy.");
}

if($_AUTH_PROVIDER == 'basic') {
  if(@$_SERVER['PHP_AUTH_USER'] !== $_AUTH_USER || !hash_equals($_AUTH_PASSWORD, @$_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Everything"');
    fail("The password was wrong.", status: 401);
  }
}
