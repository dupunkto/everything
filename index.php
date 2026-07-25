<?php
// Application entrypoint. Can serve as a catch-all for running the
// builtin PHP webserver, with the bundled .htaccess file in production.

$_ENV = getenv("ENV") ?: 'prod';
$_NOW = hrtime(true);

ob_start();

if($_ENV == 'dev') {
  error_reporting(E_ALL & ~E_DEPRECATED);
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
}

require_once __DIR__ . "/core.php";
require_once __DIR__ . "/router.php";

$_AUTHENTICATED = false;

register_shutdown_function(function() use ($_NOW, &$_AUTHENTICATED) {
  $error = error_get_last();
  $status = http_response_code();

  if($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) $status = 500;

  if(!$_AUTHENTICATED && $status == 401) {
    \logger\warn("Authentication denied.", [
      'method' => @$_SERVER['REQUEST_METHOD'],
      'uri' => @$_SERVER['REQUEST_URI'],
      'remote_addr' => @$_SERVER['REMOTE_ADDR'],
    ]);
  }

  \store\put_http_log([
    'method' => @$_SERVER['REQUEST_METHOD'] ?: 'CLI',
    'uri' => @$_SERVER['REQUEST_URI'] ?: '',
    'status' => $status ?: 200,
    'authenticated' => $_AUTHENTICATED,
    'remote_addr' => @$_SERVER['REMOTE_ADDR'],
    'user_agent' => @$_SERVER['HTTP_USER_AGENT'],
    'referer' => @$_SERVER['HTTP_REFERER'],
    'content_type' => @$_SERVER['CONTENT_TYPE'],
    'request_bytes' => @$_SERVER['CONTENT_LENGTH'],
    'response_bytes' => ob_get_length() ?: null,
    'duration_ms' => (int)((hrtime(true) - $_NOW) / 1e6),
  ]);
});

require_once __DIR__ . "/auth.php";

$_AUTHENTICATED = true;

$requested_file = path_join(__DIR__, "public", $path);

if(is_file($requested_file) and is_builtin()) {
  // Serve file as-is. Only applies to the development server,
  // in production this will be handled by Apache directly.
  serve_file($requested_file);
}

if(!is_https() and FORCE_HTTPS) {
  http_response_code(301);
  header("Location: https://" . HOST . $_SERVER['REQUEST_URI']);
  exit;
}

// TODO(robin): improve this routing
if($path == "/caldav" || str_starts_with($path, "/caldav/")) {
  include __DIR__ . "/app/caldav.php"; exit;
}

if($path == "/") $path = "/index";
$controller = path_join(__DIR__, "app", "$path.php");

if(file_exists($controller)) {
  include $controller; exit;
}

// If no response has been served yet, the requested resource
// does not exist.
serve_error(404);
