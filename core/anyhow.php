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

// Request-level database transactions

$_TRANSACTION = false;

function begin_request() {
  global $_TRANSACTION;
  if($_TRANSACTION) return;
  \DBH->beginTransaction();
  $_TRANSACTION = true;
}

function finish_request($commit) {
  global $_TRANSACTION;
  if(!$_TRANSACTION || !defined('DBH')) return;
  $_TRANSACTION = false;

  if(!\DBH->inTransaction()) return;
  if(!$commit) { \DBH->rollBack(); return; }

  try { \DBH->commit(); }
  catch(Throwable $error) {
    if(\DBH->inTransaction()) \DBH->rollBack();
    throw $error;
  }
}

function rollback_request() {
  try { finish_request(false); }
  catch(Throwable $error) { error_log("Could not roll back request transaction: " . $error->getMessage()); }
}

function clear_response() {
  while(ob_get_level()) ob_end_clean();
  ob_start();
}

// Error rendering

function render_error($error) {
  http_response_code(error_status($error));

  $uri = @$_SERVER['REQUEST_URI'] ?: '';
  if($error instanceof DAVError || str_starts_with($uri, '/caldav') || str_starts_with($uri, '/carddav')) {
    render_dav_error($error);
    return;
  }

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

// Renders a DAV error document. Bare condition names resolve to the
// protocol namespace of the request path (CalDAV or CardDAV); the D:
// prefix pins a condition to the DAV: namespace instead.
function render_dav_error($error) {
  $condition = $error instanceof DAVError ? $error->condition : null;

  if($condition) {
    header("Content-Type: application/xml; charset=utf-8");
    $carddav = str_starts_with(@$_SERVER['REQUEST_URI'] ?: '', '/carddav');
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

function handle_exception($error) {
  rollback_request();
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
    rollback_request();
    report_error($error);
    clear_response();
    render_error($error);
    return;
  }

  try { finish_request(true); }
  catch(Throwable $error) {
    report_error($error);
    clear_response();
    render_error($error);
  }
});

// HTTP logger
// (argueably doesn't below here but it was convient to put it here)

register_shutdown_function(function() {
  global $_NOW, $_AUTHENTICATED;

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
      'response_bytes' => ob_get_length() ?: null,
      'duration_ms' => (int)((hrtime(true) - $_NOW) / 1e6),
    ]);
  }
  catch(Throwable $error) {
    error_log("Could not write HTTP log: " . $error->getMessage());
  }
});
