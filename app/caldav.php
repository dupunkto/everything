<?php
// CalDAV server for calendars, subscriptions, reminders and wishlists.
// Written by Claude. (dont judge me ok.)

define('CALDAV_XML_DAV', 'DAV:');
define('CALDAV_XML_CALDAV', 'urn:ietf:params:xml:ns:caldav');
define('CALDAV_XML_SERVER', 'http://calendarserver.org/ns/');
define('CALDAV_XML_APPLE', 'http://apple.com/ns/ical/');

\caldav\reconcile();

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

  $document = new DOMDocument();
  $previous = libxml_use_internal_errors(true);
  $ok = $document->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);
  if(!$ok) dav_error(400, "Invalid XML request.");
  return $document;
}

function dav_error($status, $message, $condition = null) {
  http_response_code($status);
  if($condition) {
    header("Content-Type: application/xml; charset=utf-8");
    echo '<?xml version="1.0" encoding="utf-8"?>';
    echo '<D:error xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">';
    $prefix = str_starts_with($condition, 'D:') ? 'D' : 'C';
    echo "<$prefix:" . text(str_replace('D:', '', $condition)) . "/>";
    echo '</D:error>';
  }
  else {
    header("Content-Type: text/plain; charset=utf-8");
    echo $message;
  }
  exit;
}

function request_properties($document) {
  if(!$document || $document->documentElement->localName == 'allprop') return null;
  foreach($document->documentElement->childNodes as $child) {
    if(!$child instanceof DOMElement || $child->localName != 'prop') continue;
    $properties = [];
    foreach($child->childNodes as $property) {
      if($property instanceof DOMElement)
        $properties[] = $property->namespaceURI . '|' . $property->localName;
    }
    return $properties;
  }
  return null;
}

function property_xml($name, $value) {
  [$namespace, $local] = explode('|', $name, 2);
  $prefix = match($namespace) {
    CALDAV_XML_DAV => 'D',
    CALDAV_XML_CALDAV => 'C',
    CALDAV_XML_SERVER => 'CS',
    CALDAV_XML_APPLE => 'A',
    default => null,
  };
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

function multistatus($responses) {
  http_response_code(207);
  header("Content-Type: application/xml; charset=utf-8");
  echo '<?xml version="1.0" encoding="utf-8"?>';
  echo '<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav" '
    . 'xmlns:CS="http://calendarserver.org/ns/" xmlns:A="http://apple.com/ns/ical/">';
  echo implode('', $responses);
  echo '</D:multistatus>';
  exit;
}

function root_properties() {
  return [
    CALDAV_XML_DAV . '|resourcetype' => ['raw' => '<D:collection/>'],
    CALDAV_XML_DAV . '|displayname' => ['text' => 'Everything'],
    CALDAV_XML_DAV . '|current-user-principal' => ['raw' => '<D:href>/caldav/principals/' . CALDAV_PRINCIPAL . '/</D:href>'],
  ];
}

function principal_properties() {
  return [
    CALDAV_XML_DAV . '|resourcetype' => ['raw' => '<D:principal/>'],
    CALDAV_XML_DAV . '|displayname' => ['text' => 'Everything'],
    CALDAV_XML_DAV . '|principal-URL' => ['raw' => '<D:href>/caldav/principals/' . CALDAV_PRINCIPAL . '/</D:href>'],
    CALDAV_XML_CALDAV . '|calendar-home-set' => ['raw' => '<D:href>/caldav/calendars/' . CALDAV_PRINCIPAL . '/</D:href>'],
  ];
}

function home_properties() {
  return [
    CALDAV_XML_DAV . '|resourcetype' => ['raw' => '<D:collection/>'],
    CALDAV_XML_DAV . '|displayname' => ['text' => 'Everything calendars'],
    CALDAV_XML_DAV . '|current-user-principal' => ['raw' => '<D:href>/caldav/principals/' . CALDAV_PRINCIPAL . '/</D:href>'],
  ];
}

function collection_href($id) {
  return '/caldav/calendars/' . CALDAV_PRINCIPAL . '/' . rawurlencode($id) . '/';
}

function sync_token($collection, $revision) {
  return CANONICAL . '/caldav/sync/' . rawurlencode($collection) . '/' . $revision;
}

function collection_properties($collection) {
  $revision = \store\caldav_collection_revision($collection['id']);
  $privileges = '<D:privilege><D:read/></D:privilege>';
  if(!$collection['readonly']) $privileges .= $collection['calendar']
    ? '<D:privilege><D:write/></D:privilege>'
    : '<D:privilege><D:write-content/></D:privilege>';
  $properties = [
    CALDAV_XML_DAV . '|resourcetype' => ['raw' => '<D:collection/><C:calendar/>'],
    CALDAV_XML_DAV . '|displayname' => ['text' => $collection['displayname']],
    CALDAV_XML_DAV . '|sync-token' => ['text' => sync_token($collection['id'], $revision)],
    CALDAV_XML_DAV . '|supported-report-set' => ['raw' => '<D:supported-report><D:report><C:calendar-query/></D:report></D:supported-report>'
      . '<D:supported-report><D:report><C:calendar-multiget/></D:report></D:supported-report>'
      . '<D:supported-report><D:report><D:sync-collection/></D:report></D:supported-report>'],
    CALDAV_XML_CALDAV . '|supported-calendar-component-set' => ['raw' => '<C:comp name="' . $collection['component'] . '"/>'],
    CALDAV_XML_SERVER . '|getctag' => ['text' => (string)$revision],
    CALDAV_XML_DAV . '|current-user-privilege-set' => ['raw' => $privileges],
  ];
  if($collection['color']) $properties[CALDAV_XML_APPLE . '|calendar-color'] = ['text' => $collection['color'] . 'FF'];
  if($collection['position'] !== null) $properties[CALDAV_XML_APPLE . '|calendar-order'] = ['text' => (string)$collection['position']];
  return $properties;
}

function resource_properties($collection, $resource, $calendar_data = false) {
  $body = \caldav\serialize($resource);
  if($body === null) return null;
  $properties = [
    CALDAV_XML_DAV . '|resourcetype' => ['text' => ''],
    CALDAV_XML_DAV . '|getetag' => ['text' => \caldav\etag($body)],
    CALDAV_XML_DAV . '|getcontenttype' => ['text' => 'text/calendar; charset=utf-8; component=' . $collection['component']],
    CALDAV_XML_DAV . '|getcontentlength' => ['text' => (string)strlen($body)],
    CALDAV_XML_DAV . '|getlastmodified' => ['text' => gmdate('D, d M Y H:i:s', strtotime($resource['touched_at'])) . ' GMT'],
  ];
  if($calendar_data) $properties[CALDAV_XML_CALDAV . '|calendar-data'] = ['text' => $body];
  return $properties;
}

function locate() {
  global $path;
  $principal = preg_quote(CALDAV_PRINCIPAL, '@');

  if($path == '/caldav') return ['root'];
  if($path == '/caldav/principals/' . CALDAV_PRINCIPAL) return ['principal'];
  if($path == '/caldav/calendars/' . CALDAV_PRINCIPAL) return ['home'];
  if(preg_match("@^/caldav/calendars/$principal/([^/]+)(?:/(.+))?$@", $path, $match)) {
    $id = rawurldecode($match[1]);
    $collection = \caldav\collection($id) or dav_error(404, "Collection not found.");
    if(!isset($match[2])) return ['collection', $collection];
    $href = rawurldecode($match[2]);
    if(str_contains($href, '/')) dav_error(404, "Resource not found.");
    return ['resource', $collection, $href, \store\get_caldav_resource_by_href($id, $href)];
  }
  dav_error(404, "Not found.");
}

function propfind() {
  $depth = @$_SERVER['HTTP_DEPTH'] ?: '0';
  if(!in_array($depth, ['0', '1'])) dav_error(403, "Only Depth 0 and 1 are supported.");
  $requested = request_properties(xml_body());
  $location = locate();
  $responses = [];

  if($location[0] == 'root') {
    $responses[] = response('/caldav/', root_properties(), $requested);
    if($depth == '1') $responses[] = response('/caldav/principals/' . CALDAV_PRINCIPAL . '/', principal_properties(), $requested);
  }
  elseif($location[0] == 'principal') {
    $responses[] = response('/caldav/principals/' . CALDAV_PRINCIPAL . '/', principal_properties(), $requested);
  }
  elseif($location[0] == 'home') {
    $responses[] = response('/caldav/calendars/' . CALDAV_PRINCIPAL . '/', home_properties(), $requested);
    if($depth == '1') foreach(\caldav\collections() as $collection)
      $responses[] = response(collection_href($collection['id']), collection_properties($collection), $requested);
  }
  elseif($location[0] == 'collection') {
    $collection = $location[1];
    $responses[] = response(collection_href($collection['id']), collection_properties($collection), $requested);
    if($depth == '1') foreach(\store\list_caldav_resources_by_collection($collection['id']) as $resource)
      $responses[] = response(collection_href($collection['id']) . rawurlencode($resource['href']), resource_properties($collection, $resource), $requested);
  }
  else {
    [$kind, $collection, $name, $resource] = $location;
    if(!$resource) dav_error(404, "Resource not found.");
    $responses[] = response(collection_href($collection['id']) . rawurlencode($name), resource_properties($collection, $resource), $requested);
  }

  multistatus($responses);
}

function in_time_range($resource, $start, $end) {
  $events = ['appointment', 'travel_before', 'travel_after'];
  if(!in_array($resource['entity_type'], $events) || !$start || !$end) return true;
  $row = \caldav\entity($resource);
  $from = \DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $start, new \DateTimeZone("UTC"));
  $to = \DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $end, new \DateTimeZone("UTC"));
  if(!$from || !$to) return true;

  if($resource['entity_type'] == 'travel_before') {
    $event_end = new \DateTimeImmutable($row['starts_at']);
    $event_start = $event_end->modify('-' . (int)$row['travel_before'] . ' minutes');
  }
  elseif($resource['entity_type'] == 'travel_after') {
    $event_start = new \DateTimeImmutable($row['ends_at']);
    $event_end = $event_start->modify('+' . (int)$row['travel_after'] . ' minutes');
  }
  else {
    $event_start = new \DateTimeImmutable($row['starts_at']);
    $event_end = new \DateTimeImmutable($row['ends_at']);
  }

  if(!$row['recurrence'])
    return $event_start < $to && $event_end > $from;

  $zone = new \DateTimeZone(TIMEZONE);
  $base = $event_start->setTimezone($zone);
  $duration = $event_end->getTimestamp() - $event_start->getTimestamp();
  $window_from = $from->setTimezone($zone)->modify("-$duration seconds");
  $window_to = $to->setTimezone($zone)->modify('-1 second');
  return !!\recurrence\occurrences($row['recurrence'], $base, $window_from, $window_to);
}

function report() {
  $location = locate();
  if($location[0] != 'collection') dav_error(403, "REPORT requires a calendar collection.");
  $collection = $location[1];
  $document = xml_body() or dav_error(400, "REPORT body is required.");
  $report = $document->documentElement->localName;
  $requested = request_properties($document);
  $responses = [];
  foreach(['expand', 'limit-recurrence-set', 'limit-freebusy-set'] as $modifier)
    if($document->getElementsByTagNameNS(CALDAV_XML_CALDAV, $modifier)->length)
      dav_error(403, "Calendar data expansion is unsupported.", 'supported-calendar-data');

  if($report == 'calendar-multiget') {
    foreach($document->getElementsByTagNameNS(CALDAV_XML_DAV, 'href') as $node) {
      $name = rawurldecode(basename(parse_url($node->textContent, PHP_URL_PATH)));
      $resource = \store\get_caldav_resource_by_href($collection['id'], $name);
      $url = collection_href($collection['id']) . rawurlencode($name);
      $responses[] = $resource
        ? response($url, resource_properties($collection, $resource, calendar_data: true), $requested)
        : response($url, [], $requested, 404);
    }
  }
  elseif($report == 'calendar-query') {
    foreach(['prop-filter', 'param-filter', 'text-match', 'is-not-defined'] as $filter)
      if($document->getElementsByTagNameNS(CALDAV_XML_CALDAV, $filter)->length)
        dav_error(403, "Unsupported calendar filter.", 'valid-filter');

    $component_match = true;
    foreach($document->getElementsByTagNameNS(CALDAV_XML_CALDAV, 'comp-filter') as $filter) {
      $name = strtoupper($filter->getAttribute('name'));
      if($name != 'VCALENDAR' && $name != $collection['component']) $component_match = false;
    }
    $ranges = $document->getElementsByTagNameNS(CALDAV_XML_CALDAV, 'time-range');
    $start = $ranges->length ? $ranges->item(0)->getAttribute('start') : null;
    $end = $ranges->length ? $ranges->item(0)->getAttribute('end') : null;
    foreach(\store\list_caldav_resources_by_collection($collection['id']) as $resource) {
      if(!$component_match || !in_time_range($resource, $start, $end)) continue;
      $responses[] = response(collection_href($collection['id']) . rawurlencode($resource['href']),
        resource_properties($collection, $resource, calendar_data: true), $requested);
    }
  }
  elseif($report == 'sync-collection') {
    $tokens = $document->getElementsByTagNameNS(CALDAV_XML_DAV, 'sync-token');
    $token = $tokens->length ? trim($tokens->item(0)->textContent) : '';
    $pattern = '@/sync/' . preg_quote(rawurlencode($collection['id']), '@') . '/(\d+)$@';
    if($token && !preg_match($pattern, $token, $match))
      dav_error(403, "Invalid sync token.", 'D:valid-sync-token');
    $revision = $token ? (int)$match[1] : null;

    if($revision === null) {
      foreach(\store\list_caldav_resources_by_collection($collection['id']) as $resource)
        $responses[] = response(collection_href($collection['id']) . rawurlencode($resource['href']),
          resource_properties($collection, $resource, calendar_data: true), $requested);
    }
    else {
      if($revision > \store\caldav_global_revision()) dav_error(403, "Invalid sync token.", 'D:valid-sync-token');
      foreach(\store\list_caldav_changes($collection['id'], $revision) as $change) {
        $url = collection_href($collection['id']) . rawurlencode($change['href']);
        $resource = $change['operation'] == 'upsert'
          ? \store\get_caldav_resource_by_href($collection['id'], $change['href']) : null;
        $responses[] = $resource
          ? response($url, resource_properties($collection, $resource, calendar_data: true), $requested)
          : response($url, [], $requested, 404);
      }
    }

    $current = \store\caldav_collection_revision($collection['id']);
    http_response_code(207);
    header("Content-Type: application/xml; charset=utf-8");
    echo '<?xml version="1.0" encoding="utf-8"?>';
    echo '<D:multistatus xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">';
    echo implode('', $responses);
    echo '<D:sync-token>' . text(sync_token($collection['id'], $current)) . '</D:sync-token></D:multistatus>';
    exit;
  }
  else {
    dav_error(403, "Unsupported REPORT.", 'D:supported-report');
  }

  multistatus($responses);
}

function precondition($resource) {
  $match = @$_SERVER['HTTP_IF_MATCH'];
  $none = @$_SERVER['HTTP_IF_NONE_MATCH'];
  if($none == '*' && $resource) dav_error(412, "Resource already exists.");
  if($match) {
    if(!$resource) dav_error(412, "Resource does not exist.");
    $body = \caldav\serialize($resource);
    if($match != '*' && !in_array(\caldav\etag($body), array_map('trim', explode(',', $match))))
      dav_error(412, "ETag does not match.");
  }
}

function save_properties($type, $id, $data) {
  \store\replace_properties($type, $id, $data['properties'])
    or throw new \RuntimeException("Could not save properties.");

  foreach($data['alarms'] as &$alarm) $alarm['id'] = \generate_humid();
  unset($alarm);
  \store\replace_alarms($type, $id, $data['alarms'])
    or throw new \RuntimeException("Could not save alarms.");
  foreach($data['alarms'] as $alarm)
    \store\replace_properties('alarm', $alarm['id'], $alarm['properties'])
      or throw new \RuntimeException("Could not save alarm properties.");
}

function put() {
  $location = locate();
  if($location[0] != 'resource') dav_error(405, "PUT requires a resource URL.");
  [$kind, $collection, $name, $resource] = $location;
  if($collection['readonly']) dav_error(403, "Collection is read-only.");
  precondition($resource);

  try { $data = \caldav\parse(file_get_contents('php://input'), $collection['component'], $collection['type']); }
  catch(\InvalidArgumentException $e) { dav_error(403, $e->getMessage(), 'valid-calendar-object-resource'); }

  $type = $collection['type'];
  $by_uid = \store\get_caldav_resource_by_uid($data['uid']);
  if($by_uid && $by_uid['entity_type'] != $type)
    dav_error(409, "UID conflicts with another component type.");
  if($resource && $resource['uid'] != $data['uid'])
    dav_error(409, "UID cannot be changed.");
  if($resource && $by_uid && $resource['entity_id'] != $by_uid['entity_id'])
    dav_error(409, "UID conflicts with another resource.");
  if($resource && $resource['entity_type'] != $type)
    dav_error(403, "Component type cannot move between collection types.");
  if(!$resource && $by_uid) $resource = $by_uid;
  if($resource && $resource['entity_type'] != $type)
    dav_error(403, "Wishlist and task resources cannot be moved.");

  $created = !$resource;
  $saved_collection = $collection['id'];
  $saved_name = $name;
  try {
    \store\transaction(function() use (&$resource, &$saved_collection, &$saved_name, $collection, $name, $data, $type, $created) {
      $old_collection = @$resource['collection'];
      $old_href = @$resource['href'];

      if($created) {
        if($type == 'appointment') {
          $id = \store\put_calendar_appointment(
            $collection['id'], $data['title'], $data['content'], $data['starts_at'], $data['ends_at'],
            $data['location'], $data['meeting'], $data['recurrence'], $data['all_day']
          );
        }
        elseif($type == 'task') {
          $status = \caldav\task_status($collection['id'], $data['status'], 'todo');
          $saved_collection = \caldav\task_collection($status);
          $id = \store\put_task(
            $data['title'], $data['content'], $status, $data['urgent'], $data['recurrence'],
            $data['open_at'], $data['due_at'], $data['due_all_day']
          );
        }
        else {
          $status = $data['status'] == 'COMPLETED' ? 'bought' : 'dream';
          $id = \store\put_wish($data['title'], $data['content'], $status, $data['urgent'], $data['open_at']);
        }
        if(!$id) throw new \RuntimeException("Could not create resource.");
      }
      else {
        $id = $resource['entity_id'];
        $current = \caldav\entity($resource);
        if($type == 'appointment') {
          \store\update_appointment(
            $id, $data['title'], $data['content'], $data['starts_at'], $data['ends_at'],
            $data['location'], $data['meeting'], $data['recurrence'], $data['all_day'],
            $current['going'], $data['urgent'], $current['travel_before'], $current['travel_after'], $collection['id']
          ) or throw new \RuntimeException("Could not update appointment.");
        }
        elseif($type == 'task') {
          \store\update_task(
            $id, $data['title'], $data['content'], $data['urgent'], $data['recurrence'],
            $data['open_at'], $data['due_at'], $data['due_all_day'], $current['expire_at']
          ) or throw new \RuntimeException("Could not update task.");
          $status = \caldav\task_status($collection['id'], $data['status'], $current['status']);
          $saved_collection = \caldav\task_collection($status);
          \store\set_task_status($id, $status) or throw new \RuntimeException("Could not update task status.");
        }
        else {
          \store\update_wish($id, $data['title'], $data['content'], $data['urgent'], $data['open_at'])
            or throw new \RuntimeException("Could not update wish.");
          \store\set_wish_status($id, $data['status'] == 'COMPLETED' ? 'bought' : 'dream')
            or throw new \RuntimeException("Could not update wish status.");
        }
      }

      save_properties($type, $id, $data);
      $occupied = \store\get_caldav_resource_by_href($saved_collection, $saved_name);
      if($occupied && ($occupied['entity_type'] != $type || $occupied['entity_id'] != $id))
        $saved_name = $id . ".ics";
      \store\update_caldav_resource($type, $id, $saved_name, $saved_collection, uid: $data['uid'])
        or throw new \RuntimeException("Could not save resource href.");
      \store\touch_caldav_resource($type, $id) or throw new \RuntimeException("Could not update revision.");
      $table = $type == 'appointment' ? 'appointments' : $type . 's';
      \store\put_log($table, $id, $created ? "Created through CalDAV." : "Updated through CalDAV.", 'caldav')
        or throw new \RuntimeException("Could not create audit entry.");
      if($type == 'appointment') \caldav\mark_travel_changed($id);

      $changes = [];
      if($old_collection && ($old_collection != $saved_collection || $old_href != $saved_name))
        $changes[] = ['collection' => $old_collection, 'href' => $old_href, 'operation' => 'delete'];
      $changes[] = ['collection' => $saved_collection, 'href' => $saved_name, 'operation' => 'upsert'];
      \store\put_caldav_changes($changes) or throw new \RuntimeException("Could not update sync state.");
    });
  }
  catch(\Throwable $e) { dav_error(500, $e->getMessage()); }

  http_response_code($created ? 201 : 204);
  $saved = \store\get_caldav_resource_by_href($saved_collection, $saved_name);
  header('ETag: ' . \caldav\etag(\caldav\serialize($saved)));
  if($saved_collection != $collection['id'] || $saved_name != $name)
    header('Content-Location: ' . collection_href($saved_collection) . rawurlencode($saved_name));
  exit;
}

function delete_resource() {
  $location = locate();
  if($location[0] != 'resource' || !$location[3]) dav_error(404, "Resource not found.");
  [$kind, $collection, $name, $resource] = $location;
  if($collection['readonly']) dav_error(403, "Collection is read-only.");
  precondition($resource);

  try {
    \store\transaction(function() use ($resource) {
      $type = $resource['entity_type'];
      $id = $resource['entity_id'];
      if($type == 'appointment') {
        \store\delete_appointment($id)
          or throw new \RuntimeException("Could not delete resource.");
        \caldav\mark_resource_deleted($type, $id);
      }
      else {
        $changed = $type == 'task'
          ? \store\set_task_status($id, 'nvm')
          : \store\set_wish_status($id, 'nvm');
        $changed or throw new \RuntimeException("Could not delete resource.");
        \caldav\hide_resource($type, $id);
      }

      $table = $type == 'appointment' ? 'appointments' : $type . 's';
      \store\put_log($table, $id, "Deleted through CalDAV.", 'caldav')
        or throw new \RuntimeException("Could not create audit entry.");
    });
  }
  catch(\Throwable $e) { dav_error(500, $e->getMessage()); }

  http_response_code(204); exit;
}

function move() {
  $location = locate();
  if($location[0] != 'resource' || !$location[3]) dav_error(404, "Resource not found.");
  [$kind, $source, $name, $resource] = $location;
  precondition($resource);
  $destination = @$_SERVER['HTTP_DESTINATION'] or dav_error(400, "Destination is required.");
  $destination_path = '/' . trim(parse_url($destination, PHP_URL_PATH), '/');
  $principal = preg_quote(CALDAV_PRINCIPAL, '@');
  if(!preg_match("@^/caldav/calendars/$principal/([^/]+)/([^/]+)$@", $destination_path, $match))
    dav_error(403, "Invalid destination.");
  $target = \caldav\collection(rawurldecode($match[1])) or dav_error(404, "Destination collection not found.");
  $target_name = rawurldecode($match[2]);
  if($source['readonly'] || $target['readonly'])
    dav_error(403, "Collection is read-only.");
  if($target['type'] != $source['type']) dav_error(403, "Wishlist and task resources cannot be moved.");
  if($target['type'] == 'wish') dav_error(403, "Wishlist cannot be moved.");
  if(\store\get_caldav_resource_by_href($target['id'], $target_name)) dav_error(412, "Destination exists.");

  try {
    \store\transaction(function() use ($source, $target, $name, $target_name, $resource) {
      $id = $resource['entity_id'];
      if($resource['entity_type'] == 'appointment') {
        $row = \caldav\entity($resource);
        \store\update_appointment(
          $id, $row['title'], $row['content'], $row['starts_at'], $row['ends_at'], $row['location'],
          $row['meeting'], $row['recurrence'], $row['all_day'], $row['going'], $row['urgent'],
          $row['travel_before'], $row['travel_after'], $target['id']
        ) or throw new \RuntimeException("Could not move appointment.");
      }
      else {
        $row = \caldav\entity($resource);
        $status = \caldav\task_status($target['id'], $row['status'] == 'done' ? 'COMPLETED' : 'NEEDS-ACTION', $row['status']);
        \store\set_task_status($id, $status) or throw new \RuntimeException("Could not move task.");
      }
      \store\touch_caldav_resource($resource['entity_type'], $id)
        or throw new \RuntimeException("Could not update revision.");
      $table = $resource['entity_type'] == 'appointment' ? 'appointments' : 'tasks';
      \store\put_log($table, $id, "Moved through CalDAV.", 'caldav')
        or throw new \RuntimeException("Could not create audit entry.");
      if($resource['entity_type'] == 'appointment') \caldav\mark_travel_changed($id);
      \store\update_caldav_resource($resource['entity_type'], $id, $target_name, $target['id'])
        or throw new \RuntimeException("Could not move resource href.");
      \store\put_caldav_changes([
        ['collection' => $source['id'], 'href' => $name, 'operation' => 'delete'],
        ['collection' => $target['id'], 'href' => $target_name, 'operation' => 'upsert'],
      ]) or throw new \RuntimeException("Could not update sync state.");
    });
  }
  catch(\Throwable $e) { dav_error(500, $e->getMessage()); }

  http_response_code(201); exit;
}

function proppatch() {
  $location = locate();
  if($location[0] != 'collection') dav_error(405, "PROPPATCH requires a collection.");
  $collection = $location[1];
  if(!$collection['calendar']) dav_error(403, "Collection properties are read-only.", 'D:cannot-modify-protected-property');
  $document = xml_body() or dav_error(400, "PROPPATCH body is required.");
  $title = $collection['title'];
  $subtitle = $collection['calendar']['subtitle'];
  $color = $collection['color'];
  $position = $collection['position'];

  foreach($document->getElementsByTagNameNS('*', '*') as $element) {
    if($element->namespaceURI == CALDAV_XML_DAV && $element->localName == 'displayname')
      [$title, $subtitle] = \caldav\parse_displayname($element->textContent);
    elseif($element->namespaceURI == CALDAV_XML_APPLE && $element->localName == 'calendar-color') $color = substr(trim($element->textContent), 0, 7);
    elseif($element->namespaceURI == CALDAV_XML_APPLE && $element->localName == 'calendar-order') $position = (int)$element->textContent;
  }
  if(!$title || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) dav_error(409, "Invalid calendar properties.");
  \store\transaction(function() use ($collection, $title, $subtitle, $color, $position) {
    \store\update_calendar(
      $collection['id'], $title, $subtitle, strtolower($color), position: $position
    ) or dav_error(500, "Could not update calendar.");
    \store\put_log('calendars', $collection['id'], "Updated calendar through CalDAV.", 'caldav')
      or dav_error(500, "Could not create audit entry.");
  });
  multistatus([response(collection_href($collection['id']), collection_properties(\caldav\collection($collection['id'])), null)]);
}

function get_resource($head = false) {
  $location = locate();
  if($location[0] != 'resource' || !$location[3]) dav_error(404, "Resource not found.");
  [$kind, $collection, $name, $resource] = $location;
  $body = \caldav\serialize($resource);
  $etag = \caldav\etag($body);
  if(@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag) { http_response_code(304); exit; }
  header("Content-Type: text/calendar; charset=utf-8");
  header("Content-Length: " . strlen($body));
  header("ETag: $etag");
  if(!$head) echo $body;
  exit;
}

function options() {
  header("Allow: OPTIONS, PROPFIND, REPORT, GET, HEAD, PUT, DELETE, MOVE, PROPPATCH");
  header("DAV: 1, 3, calendar-access, sync-collection");
  http_response_code(204); exit;
}

match($_SERVER['REQUEST_METHOD']) {
  'PROPFIND' => propfind(),
  'REPORT' => report(),
  'GET' => get_resource(),
  'HEAD' => get_resource(head: true),
  'PUT' => put(),
  'DELETE' => delete_resource(),
  'MOVE' => move(),
  'PROPPATCH' => proppatch(),
  'OPTIONS' => options(),
  default => dav_error(405, "Method not allowed."),
};
