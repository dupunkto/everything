<?php
// Pretty decent routing based on regexes.
//
// This files exposes two globals:
//
//   $path (string) is the request path, after applying normalization.
//   $params (array) contains capture groups from the route regex.
//

$path = $_SERVER['REQUEST_URI'];
$path = explode("?", $path)[0];
$path = "/" . trim($path, "/");

$params = [];

function host($hostname) {
  return $_SERVER['HTTP_HOST'] == $hostname;
}

function route($pattern) {
  global $path, $params;
  return preg_match("@$pattern@", $path, $params);
}

function is_builtin() {
  return php_sapi_name() == "cli-server";
}

function is_https() {
  return !empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== "off";
}

// Helpers for sending responses

define('FORBIDDEN_MIMES', ["application/x-httpd-php"]);

function serve_file($path) {
  $mime_type = path_mime($path) ?? "text/html";

  if(in_array($mime_type, FORBIDDEN_MIMES)) {
    serve_error(403);
  }

  header("Content-Type: {$mime_type}");
  include $path; exit;
}

function serve_error($code) {
  $mapping = [
    401 => "please dont :|",
    403 => "bad boy >:(",
    404 => "not found :(",
    500 => "everything crashed :["
  ];

  fail($mapping[$code], $code);
}

function fail($message, $status = 500) {
  http_response_code($status);
  header("Content-Type: text/plain");
  echo $message;
  exit;
}
