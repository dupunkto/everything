<?php
// Application entrypoint. Can serve as a catch-all for running the
// builtin PHP webserver, with the bundled .htaccess file in production.

$_ENV = getenv("ENV") ?: 'prod';
$_NOW = hrtime(true);

ob_start();

if($_ENV == 'dev') {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
}

require_once __DIR__ . "/core.php";
require_once __DIR__ . "/router.php";

$_AUTHENTICATED = false;

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

if(!in_array($method, ['GET', 'HEAD', 'OPTIONS', 'PROPFIND', 'REPORT'])) {
  begin_request();
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

$controller = path_join(__DIR__, "app", $path, "index.php");

if(file_exists($controller)) {
  include $controller; exit;
}

// If no response has been served yet, the requested resource
// does not exist.
fail("Not found.", status: 404);
