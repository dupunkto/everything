<?php
// Shared WebDAV plumbing for the CalDAV and CardDAV endpoints: secure XML
// parsing, property rendering, multistatus responses, ETag preconditions
// and sync tokens. Protocol-specific report logic stays in app/.

namespace webdav {

define('WEBDAV_PRINCIPAL', 'everything');

define('WEBDAV_XML_DAV', 'DAV:');
define('WEBDAV_XML_CALDAV', 'urn:ietf:params:xml:ns:caldav');
define('WEBDAV_XML_CARDDAV', 'urn:ietf:params:xml:ns:carddav');
define('WEBDAV_XML_SERVER', 'http://calendarserver.org/ns/');
define('WEBDAV_XML_APPLE', 'http://apple.com/ns/ical/');

define('WEBDAV_XML_PREFIXES', [
  WEBDAV_XML_DAV => 'D',
  WEBDAV_XML_CALDAV => 'C',
  WEBDAV_XML_CARDDAV => 'CARD',
  WEBDAV_XML_SERVER => 'CS',
  WEBDAV_XML_APPLE => 'A',
]);

function href($path) {
  return htmlspecialchars($path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function text($value) {
  return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xml_body() {
  $body = file_get_contents('php://input');
  if(!$body) return null;
  if(stripos($body, '<!DOCTYPE') !== false) dav_error(400, "DTD is not allowed.");

  $document = new \DOMDocument();
  $previous = libxml_use_internal_errors(true);
  $ok = $document->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS);
  $error = libxml_get_last_error();
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if(!$ok) {
    $detail = $error ? trim($error->message) . " at line {$error->line}, column {$error->column}" : "unknown parser error";
    dav_error(400, "Invalid XML request: $detail.");
  }
  return $document;
}

function request_properties($document) {
  if(!$document || $document->documentElement->localName == 'allprop') return null;
  foreach($document->documentElement->childNodes as $child) {
    if(!$child instanceof \DOMElement || $child->localName != 'prop') continue;
    $properties = [];
    foreach($child->childNodes as $property) {
      if($property instanceof \DOMElement)
        $properties[] = $property->namespaceURI . '|' . $property->localName;
    }
    return $properties;
  }
  return null;
}

function property_xml($name, $value) {
  [$namespace, $local] = explode('|', $name, 2);
  $prefix = @WEBDAV_XML_PREFIXES[$namespace];
  $content = @$value['raw'] ?: text(@$value['text']);
  if(!$prefix) return '<X:' . text($local) . ' xmlns:X="' . text($namespace) . '">' . $content . '</X:' . text($local) . '>';
  return "<$prefix:$local>$content</$prefix:$local>";
}

function response($href, $available, $requested = null, $status = 200) {
  if($status != 200) return '<D:response><D:href>' . href($href) . '</D:href><D:status>HTTP/1.1 '
    . $status . ' ' . ($status == 404 ? 'Not Found' : 'Error') . '</D:status></D:response>';

  $known = [];
  $missing = [];
  foreach($requested ?? array_keys($available) as $name) {
    if(isset($available[$name])) $known[$name] = $available[$name];
    else $missing[] = $name;
  }

  $xml = '<D:response><D:href>' . href($href) . '</D:href>';
  if($known) {
    $xml .= '<D:propstat><D:prop>';
    foreach($known as $name => $value) $xml .= property_xml($name, $value);
    $xml .= '</D:prop><D:status>HTTP/1.1 200 OK</D:status></D:propstat>';
  }
  if($missing) {
    $xml .= '<D:propstat><D:prop>';
    foreach($missing as $name) $xml .= property_xml($name, ['text' => '']);
    $xml .= '</D:prop><D:status>HTTP/1.1 404 Not Found</D:status></D:propstat>';
  }
  return $xml . '</D:response>';
}

function namespace_declarations() {
  $xml = '';
  foreach(WEBDAV_XML_PREFIXES as $namespace => $prefix)
    $xml .= ' xmlns:' . $prefix . '="' . $namespace . '"';
  return $xml;
}

function multistatus($responses, $footer = '') {
  http_response_code(207);
  header("Content-Type: application/xml; charset=utf-8");
  echo '<?xml version="1.0" encoding="utf-8"?>';
  echo '<D:multistatus' . namespace_declarations() . '>';
  echo implode('', $responses);
  echo $footer;
  echo '</D:multistatus>';
  exit;
}

function etag($body) {
  return '"' . hash('sha256', $body) . '"';
}

// If-Match / If-None-Match preconditions against the current ETag,
// which is null when the resource does not exist yet.
function precondition($etag) {
  $match = @$_SERVER['HTTP_IF_MATCH'];
  $none = @$_SERVER['HTTP_IF_NONE_MATCH'];
  if($none == '*' && $etag !== null)
    dav_error(412, "Resource already exists with ETag $etag.");
  if($match) {
    if($etag === null) dav_error(412, "If-Match '$match' was supplied, but the resource does not exist.");
    if($match != '*' && !in_array($etag, array_map('trim', explode(',', $match))))
      dav_error(412, "If-Match '$match' does not match current ETag $etag.");
  }
}

function sync_token($protocol, $collection, $revision) {
  return CANONICAL . "/$protocol/sync/" . rawurlencode($collection) . '/' . $revision;
}

// Resolves a client sync token to its revision: null for an empty token
// (initial sync), false when the token does not belong to this collection.
function sync_revision($token, $collection) {
  if(!$token) return null;
  $pattern = '@/sync/' . preg_quote(rawurlencode($collection), '@') . '/(\d+)$@';
  if(!preg_match($pattern, $token, $match)) return false;
  return (int)$match[1];
}

function root_properties($protocol) {
  return [
    WEBDAV_XML_DAV . '|resourcetype' => ['raw' => '<D:collection/>'],
    WEBDAV_XML_DAV . '|displayname' => ['text' => 'Everything'],
    WEBDAV_XML_DAV . '|current-user-principal' => ['raw' => '<D:href>/' . $protocol . '/principals/' . WEBDAV_PRINCIPAL . '/</D:href>'],
  ];
}

function principal_properties($protocol, $home_property, $home_path) {
  return [
    WEBDAV_XML_DAV . '|resourcetype' => ['raw' => '<D:principal/>'],
    WEBDAV_XML_DAV . '|displayname' => ['text' => 'Everything'],
    WEBDAV_XML_DAV . '|principal-URL' => ['raw' => '<D:href>/' . $protocol . '/principals/' . WEBDAV_PRINCIPAL . '/</D:href>'],
    $home_property => ['raw' => '<D:href>' . href($home_path) . '</D:href>'],
  ];
}

function home_properties($protocol, $displayname) {
  return [
    WEBDAV_XML_DAV . '|resourcetype' => ['raw' => '<D:collection/>'],
    WEBDAV_XML_DAV . '|displayname' => ['text' => $displayname],
    WEBDAV_XML_DAV . '|current-user-principal' => ['raw' => '<D:href>/' . $protocol . '/principals/' . WEBDAV_PRINCIPAL . '/</D:href>'],
  ];
}

}

namespace {

function dav_error($status, $message, $condition = null) {
  throw new DAVError($message, $status, $condition);
}

}
