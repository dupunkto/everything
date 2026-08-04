<?php

error_reporting(E_ALL & ~E_DEPRECATED);

class HTTPError extends RuntimeException {
  public $status;

  public function __construct($message, $status = 500, $previous = null) {
    parent::__construct($message, previous: $previous);
    $this->status = $status;
  }
}

class DAVError extends HTTPError {
  public $condition;

  public function __construct($message, $status = 500, $condition = null, $previous = null) {
    parent::__construct($message, $status, $previous);
    $this->condition = $condition;
  }
}

function fail($message, $status = 500) {
  throw new HTTPError($message, $status);
}

// Helpers for working with errors

function error_status($error) {
  return $error instanceof HTTPError ? $error->status : 500;
}

function error_title($error) {
  return match(error_status($error)) {
    401 => "please dont :|",
    403 => "bad boy >:(",
    404 => "not found :(",
    default => "everything crashed :[",
  };
}

function error_message($error) {
  $developer = defined('DEVELOPER_MODE') && DEVELOPER_MODE;
  $safe = $error instanceof HTTPError && $error->status < 500;

  return $developer || $safe ? $error->getMessage() : "Something went wrong.";
}

function error_context($error) {
  return [
    'status' => error_status($error),
    'error' => get_class($error),
    'message' => $error->getMessage(),
    'file' => $error->getFile(),
    'line' => $error->getLine(),
    'method' => @$_SERVER['REQUEST_METHOD'],
    'uri' => @$_SERVER['REQUEST_URI'],
    'remote_addr' => @$_SERVER['REMOTE_ADDR'],
    'trace' => $error->getTraceAsString(),
  ];
}

function report_error($error) {
  static $reporting = false;
  if($reporting) return;
  $reporting = true;

  $status = error_status($error);
  $level = match(true) {
    in_array($status, [401, 404]) => 'debug',
    $status >= 400 && $status < 500 => 'warn',
    default => 'error',
  };

  try {
    if(function_exists('logger\\write')) {
      \logger\write($level, $error->getMessage(), error_context($error));
      $reporting = false;
      return;
    }
    if(function_exists('store\\put_system_log') && defined('DBH')) {
      \store\put_system_log($level, $error->getMessage(), error_context($error));
      $reporting = false;
      return;
    }
  }
  catch(Throwable $logging_error) {
    error_log("Could not write error log: " . $logging_error->getMessage());
  }

  error_log((string)$error);
  $reporting = false;
}

// Request lifecycle.

$_REQUEST_HOOKS = [];
$_REQUEST_BEGUN = false;

function bracket_request($hooks) {
  global $_REQUEST_HOOKS;
  foreach($hooks as $name => $callback)
    $_REQUEST_HOOKS[$name][] = $callback;
}

function request_hook($name, ...$args) {
  global $_REQUEST_HOOKS;
  foreach($_REQUEST_HOOKS[$name] ?? [] as $callback)
    $callback(...$args);
}

function begin_request() {
  global $_REQUEST_BEGUN, $method, $path;
  if($_REQUEST_BEGUN) return;
  $_REQUEST_BEGUN = true;

  request_hook('guard', $method, $path);
  request_hook('begin');
}

// The failure path: roll back and release whatever the request brackets
// hold. One hook failing must not keep the next from running.
function abandon_request() {
  foreach(['rollback', 'abandon'] as $hook) {
    try { request_hook($hook); }
    catch(Throwable $error) { error_log("Could not run request hook '$hook': " . $error->getMessage()); }
  }
}

function clear_response() {
  while(ob_get_level()) ob_end_clean();
  ob_start();
}

// Response buffering

$_RESPONSE_FLUSHED = false;
$_RESPONSE_BYTES = null;

function flush_response() {
  global $_RESPONSE_FLUSHED, $_RESPONSE_BYTES;
  if($_RESPONSE_FLUSHED) return;
  $_RESPONSE_FLUSHED = true;
  $_RESPONSE_BYTES = ob_get_length() ?: 0;

  ignore_user_abort(true);
  if(!headers_sent()) {
    header("Content-Length: " . $_RESPONSE_BYTES);
    header("Connection: close");
  }
  while(ob_get_level()) ob_end_flush();
  flush();
  if(function_exists('fastcgi_finish_request')) fastcgi_finish_request();
}

// Error rendering

function render_error($error) {
  $uri = @$_SERVER['REQUEST_URI'];

  match(true) {
    php_sapi_name() == 'cli' => render_cli_error($error),
    $error instanceof DAVError => render_dav_error($error),
    str_starts_with($uri, '/caldav') => render_dav_error($error),
    str_starts_with($uri, '/carddav') => render_dav_error($error),
    default => render_html_error($error),
  };
}

function render_cli_error($error) {
  fwrite(STDERR, $error->getMessage() . "\n");
  if(defined('DEVELOPER_MODE') && DEVELOPER_MODE)
    fwrite(STDERR, $error->getTraceAsString() . "\n");
  exit(1);
}

function render_dav_error($error) {
  http_response_code(error_status($error));
  $condition = $error instanceof DAVError ? $error->condition : null;

  if($condition) {
    header("Content-Type: application/xml; charset=utf-8");
    $carddav = str_starts_with($_SERVER['REQUEST_URI'], '/carddav');
    $protocol_prefix = $carddav ? 'CARD' : 'C';
    $protocol_namespace = $carddav ? 'urn:ietf:params:xml:ns:carddav' : 'urn:ietf:params:xml:ns:caldav';
    $name = str_replace('D:', '', $condition);
    $prefix = str_starts_with($condition, 'D:') ? 'D' : $protocol_prefix;
    $name = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    echo '<?xml version="1.0" encoding="utf-8"?>';
    echo "<D:error xmlns:D=\"DAV:\" xmlns:$protocol_prefix=\"$protocol_namespace\">";
    echo "<$prefix:$name/>";
    echo '</D:error>';
    return;
  }

  header("Content-Type: text/plain; charset=utf-8");
  echo error_message($error);
}

function render_html_error($error) {
  http_response_code(error_status($error));
  header("Content-Type: text/html; charset=utf-8");

  $status = error_status($error);
  $title = error_title($error);
  $message = error_message($error);
  $developer = defined('DEVELOPER_MODE') && DEVELOPER_MODE;
  $fragment = @$_SERVER['HTTP_X_XHTML'] == 'true';

  include $fragment ?
    __DIR__ . "/../app/error/fragment.php" :
    __DIR__ . "/../app/error/page.php";
}

function handle_exception($error) {
  abandon_request();
  report_error($error);
  clear_response();
  render_error($error);
}

// Plumbing

set_error_handler(function($severity, $message, $file, $line) {
  if(!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler('handle_exception');

register_shutdown_function(function() {
  $failure = error_get_last();
  $fatal = $failure && in_array($failure['type'], [
    E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR
  ]);

  if($fatal) {
    $error = new ErrorException($failure['message'], 0, $failure['type'], $failure['file'], $failure['line']);
    abandon_request();
    report_error($error);
    clear_response();
    render_error($error);
    return;
  }

  global $_RESPONSE_FLUSHED;
  $committed = false;
  try {
    request_hook('before_commit');
    request_hook('commit');
    $committed = true;
    request_hook('committed');
    request_hook('after_commit');
  }
  catch(Throwable $error) {
    if(!$committed) abandon_request();
    report_error($error);
    if($_RESPONSE_FLUSHED) return;
    clear_response();
    render_error($error);
  }
});

// HTTP logger
// (argueably doesn't below here but it was convient to put it here)

register_shutdown_function(function() {
  global $_NOW, $_AUTHENTICATED, $_RESPONSE_BYTES;

  $failure = error_get_last();
  $status = http_response_code();
  if($failure && in_array($failure['type'], [
    E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR
  ])) $status = 500;

  try {
    \store\put_http_log([
      'method' => @$_SERVER['REQUEST_METHOD'] ?: 'CLI',
      'uri' => @$_SERVER['REQUEST_URI'] ?: '',
      'status' => $status ?: 200,
      'authenticated' => @$_AUTHENTICATED ?: false,
      'remote_addr' => @$_SERVER['REMOTE_ADDR'],
      'user_agent' => @$_SERVER['HTTP_USER_AGENT'],
      'referer' => @$_SERVER['HTTP_REFERER'],
      'content_type' => @$_SERVER['CONTENT_TYPE'],
      'request_bytes' => @$_SERVER['CONTENT_LENGTH'],
      'response_bytes' => ob_get_length() ?: $_RESPONSE_BYTES ?: null,
      'duration_ms' => (int)((hrtime(true) - $_NOW) / 1e6),
    ]);
  }
  catch(Throwable $error) {
    error_log("Could not write HTTP log: " . $error->getMessage());
  }
});
