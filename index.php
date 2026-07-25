<?php
// Application entrypoint. Can serve as a catch-all for running the
// builtin PHP webserver, with the bundled .htaccess file in production.

$_ENV = getenv("ENV") ?: 'prod';

if($_ENV == 'dev') {
  error_reporting(E_ALL & ~E_DEPRECATED);
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
}

require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/core.php";
require_once __DIR__ . "/router.php";

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
