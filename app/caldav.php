<?php
// CalDAV server for calendars, subscriptions, reminders and wishlists.
// Written by Claude. (dont judge me ok.)

use function webdav\href, webdav\text, webdav\xml_body, webdav\request_properties,
  webdav\response, webdav\multistatus;

\caldav\reconcile();

function collection_href($id) {
  return '/caldav/calendars/' . CALDAV_PRINCIPAL . '/' . rawurlencode($id) . '/';
}

function caldav_entity_table($type) {
  return match($type) {
    'appointment' => 'appointments',
    'task' => 'tasks',
    'wish' => 'wishes',
  };
}

function collection_properties($collection) {
  $revision = \store\caldav_collection_revision($collection['id']);
  $privileges = '<D:privilege><D:read/></D:privilege>';
  if(!$collection['readonly']) $privileges .= $collection['calendar']
    ? '<D:privilege><D:write/></D:privilege>'
    : '<D:privilege><D:write-content/></D:privilege>';
  $resource_type = '<D:collection/><C:calendar/>';
  if($collection['subscription']) $resource_type .= '<CS:subscribed/>';
  $properties = [
    WEBDAV_XML_DAV . '|resourcetype' => ['raw' => $resource_type],
    WEBDAV_XML_DAV . '|displayname' => ['text' => $collection['displayname']],
    WEBDAV_XML_DAV . '|sync-token' => ['text' => \webdav\sync_token('caldav', $collection['id'], $revision)],
    WEBDAV_XML_DAV . '|supported-report-set' => ['raw' => '<D:supported-report><D:report><C:calendar-query/></D:report></D:supported-report>'
      . '<D:supported-report><D:report><C:calendar-multiget/></D:report></D:supported-report>'
      . '<D:supported-report><D:report><D:sync-collection/></D:report></D:supported-report>'],
    WEBDAV_XML_CALDAV . '|supported-calendar-component-set' => ['raw' => '<C:comp name="' . $collection['component'] . '"/>'],
    WEBDAV_XML_SERVER . '|getctag' => ['text' => (string)$revision],
    WEBDAV_XML_DAV . '|current-user-privilege-set' => ['raw' => $privileges],
  ];
  if($collection['subscription']) $properties[WEBDAV_XML_SERVER . '|source'] = [
    'raw' => '<D:href>' . href($collection['subscription']['url']) . '</D:href>',
  ];
  if($collection['color']) $properties[WEBDAV_XML_APPLE . '|calendar-color'] = ['text' => $collection['color'] . 'FF'];
  if($collection['position'] !== null) $properties[WEBDAV_XML_APPLE . '|calendar-order'] = ['text' => (string)$collection['position']];
  return $properties;
}

function resource_properties($collection, $resource, $calendar_data = false) {
  $body = \caldav\serialize($resource);
  if($body === null) return null;
  $properties = [
    WEBDAV_XML_DAV . '|resourcetype' => ['text' => ''],
    WEBDAV_XML_DAV . '|getetag' => ['text' => \webdav\etag($body)],
    WEBDAV_XML_DAV . '|getcontenttype' => ['text' => 'text/calendar; charset=utf-8; component=' . $collection['component']],
    WEBDAV_XML_DAV . '|getcontentlength' => ['text' => (string)strlen($body)],
    WEBDAV_XML_DAV . '|getlastmodified' => ['text' => gmdate('D, d M Y H:i:s', strtotime($resource['touched_at'])) . ' GMT'],
  ];
  if($calendar_data) $properties[WEBDAV_XML_CALDAV . '|calendar-data'] = ['text' => $body];
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
    $collection = \caldav\collection($id) or dav_error(404, "CalDAV collection '$id' was not found.");
    if(!isset($match[2])) return ['collection', $collection];
    $href = rawurldecode($match[2]);
    if(str_contains($href, '/')) dav_error(404, "Invalid CalDAV resource path '$href'.");
    return ['resource', $collection, $href, \store\get_caldav_resource_by_href($id, $href)];
  }
  dav_error(404, "CalDAV path '$path' was not found.");
}

function propfind() {
  $depth = @$_SERVER['HTTP_DEPTH'] ?: '0';
  if(!in_array($depth, ['0', '1'])) dav_error(403, "PROPFIND Depth '$depth' is unsupported; use 0 or 1.");
  $requested = request_properties(xml_body());
  $location = locate();
  $responses = [];

  $principal_properties = \webdav\principal_properties('caldav',
    WEBDAV_XML_CALDAV . '|calendar-home-set', '/caldav/calendars/' . CALDAV_PRINCIPAL . '/');

  if($location[0] == 'root') {
    $responses[] = response('/caldav/', \webdav\root_properties('caldav'), $requested);
    if($depth == '1') $responses[] = response('/caldav/principals/' . CALDAV_PRINCIPAL . '/', $principal_properties, $requested);
  }
  elseif($location[0] == 'principal') {
    $responses[] = response('/caldav/principals/' . CALDAV_PRINCIPAL . '/', $principal_properties, $requested);
  }
  elseif($location[0] == 'home') {
    $responses[] = response('/caldav/calendars/' . CALDAV_PRINCIPAL . '/', \webdav\home_properties('caldav', 'Everything calendars'), $requested);
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
    if(!$resource) dav_error(404, "CalDAV resource '$name' was not found in collection '{$collection['id']}'.");
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
  if($location[0] != 'collection')
    dav_error(403, "REPORT path must name a calendar collection; received {$location[0]}.");
  $collection = $location[1];
  $document = xml_body() or dav_error(400, "REPORT body is required.");
  $report = $document->documentElement->localName;
  $requested = request_properties($document);
  $responses = [];
  foreach(['expand', 'limit-recurrence-set', 'limit-freebusy-set'] as $modifier)
    if($document->getElementsByTagNameNS(WEBDAV_XML_CALDAV, $modifier)->length)
      \logger\warn("Ignored unsupported CalDAV calendar data modifier $modifier.");

  if($report == 'calendar-multiget') {
    foreach($document->getElementsByTagNameNS(WEBDAV_XML_DAV, 'href') as $node) {
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
      if($document->getElementsByTagNameNS(WEBDAV_XML_CALDAV, $filter)->length)
        \logger\warn("Ignored unsupported CalDAV calendar filter $filter.");

    $component_match = true;
    foreach($document->getElementsByTagNameNS(WEBDAV_XML_CALDAV, 'comp-filter') as $filter) {
      $name = strtoupper($filter->getAttribute('name'));
      if($name != 'VCALENDAR' && $name != $collection['component']) $component_match = false;
    }
    $ranges = $document->getElementsByTagNameNS(WEBDAV_XML_CALDAV, 'time-range');
    $start = $ranges->length ? $ranges->item(0)->getAttribute('start') : null;
    $end = $ranges->length ? $ranges->item(0)->getAttribute('end') : null;
    foreach(\store\list_caldav_resources_by_collection($collection['id']) as $resource) {
      if(!$component_match || !in_time_range($resource, $start, $end)) continue;
      $responses[] = response(collection_href($collection['id']) . rawurlencode($resource['href']),
        resource_properties($collection, $resource, calendar_data: true), $requested);
    }
  }
  elseif($report == 'sync-collection') {
    $tokens = $document->getElementsByTagNameNS(WEBDAV_XML_DAV, 'sync-token');
    $token = $tokens->length ? trim($tokens->item(0)->textContent) : '';
    $revision = \webdav\sync_revision($token, $collection['id']);
    if($revision === false)
      dav_error(403, "Sync token '$token' is invalid for collection '{$collection['id']}'.", 'D:valid-sync-token');

    if($revision === null) {
      foreach(\store\list_caldav_resources_by_collection($collection['id']) as $resource)
        $responses[] = response(collection_href($collection['id']) . rawurlencode($resource['href']),
          resource_properties($collection, $resource, calendar_data: true), $requested);
    }
    else {
      if($revision > \store\caldav_global_revision())
        dav_error(403, "Sync revision $revision is newer than the server revision " . \store\caldav_global_revision() . ".", 'D:valid-sync-token');
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
    multistatus($responses,
      '<D:sync-token>' . text(\webdav\sync_token('caldav', $collection['id'], $current)) . '</D:sync-token>');
  }
  else {
    dav_error(403, "CalDAV REPORT '$report' is unsupported.", 'D:supported-report');
  }

  multistatus($responses);
}

function precondition($resource) {
  \webdav\precondition($resource ? \webdav\etag(\caldav\serialize($resource)) : null);
}

function save_properties($type, $id, $data) {
  \store\replace_properties($type, $id, $data['properties']);

  // Alarm rows get fresh ids on every write, so retained alarm properties
  // are diffed as one aggregated multiset per parent entity.
  $previous = [];
  foreach(\store\list_alarms($type, $id) as $alarm)
    $previous = [...$previous, ...\store\list_properties('alarm', $alarm['id'])];

  foreach($data['alarms'] as &$alarm) $alarm['id'] = \generate_humid();
  unset($alarm);
  \store\replace_alarms($type, $id, $data['alarms']);

  $stored = [];
  foreach($data['alarms'] as $alarm) {
    \store\replace_properties('alarm', $alarm['id'], $alarm['properties'], log: false);
    $stored = [...$stored, ...\store\list_properties('alarm', $alarm['id'])];
  }
  \store\log_property_changes("$type/$id/alarms", $previous, $stored);
}

function put() {
  $location = locate();
  if($location[0] != 'resource') dav_error(405, "PUT path must name a resource inside a calendar collection.");
  [$kind, $collection, $name, $resource] = $location;
  if($collection['readonly'])
    dav_error(403, "CalDAV collection '{$collection['id']}' is read-only.");
  precondition($resource);

  try { $data = \caldav\parse(file_get_contents('php://input'), $collection['component'], $collection['type']); }
  catch(\InvalidArgumentException $e) { dav_error(403, $e->getMessage(), 'valid-calendar-object-resource'); }

  $type = $collection['type'];
  $by_uid = \store\get_caldav_resource_by_uid($data['uid']);
  if($by_uid && $by_uid['entity_type'] != $type)
    dav_error(409, "UID '{$data['uid']}' belongs to {$by_uid['entity_type']} {$by_uid['entity_id']}, not $type.");
  if($resource && $resource['uid'] != $data['uid'])
    dav_error(409, "Resource '$name' has UID '{$resource['uid']}'; it cannot be changed to '{$data['uid']}'.");
  if($resource && $by_uid && $resource['entity_id'] != $by_uid['entity_id'])
    dav_error(409, "UID '{$data['uid']}' already belongs to resource '{$by_uid['href']}'.");
  if($resource && $resource['entity_type'] != $type)
    dav_error(403, "Resource type {$resource['entity_type']} cannot be stored in a $type collection.");
  if(!$resource && $by_uid) $resource = $by_uid;
  if($resource && $resource['entity_type'] != $type)
    dav_error(403, "Existing {$resource['entity_type']} resource {$resource['entity_id']} cannot be stored as $type.");

  $created = !$resource;
  $saved_collection = $collection['id'];
  $saved_name = $name;
  $old_collection = @$resource['collection'];
  $old_href = @$resource['href'];

  $fields = [];
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
  }
  else {
    $id = $resource['entity_id'];
    $current = \caldav\entity($resource);
    if($type == 'appointment') {
      $fields = \core\diff($current,
        title: $data['title'], content: $data['content'],
        starts_at: $data['starts_at'], ends_at: $data['ends_at'],
        location: $data['location'], meeting: $data['meeting'],
        recurrence: $data['recurrence'], all_day: $data['all_day'],
        urgent: $data['urgent'], calendar_id: $collection['id']);
      \store\update_appointment(
        $id, $data['title'], $data['content'], $data['starts_at'], $data['ends_at'],
        $data['location'], $data['meeting'], $data['recurrence'], $data['all_day'],
        $current['going'], $data['urgent'], $current['travel_before'], $current['travel_after'], $collection['id']
      );
    }
    elseif($type == 'task') {
      $status = \caldav\task_status($collection['id'], $data['status'], $current['status']);

      // Apple rewrites DTSTART datetimes as dates matching DUE. We intentionally
      // ignore this update, even though it's technically valid, because it's wrong.
      $open_at = $data['has_start'] && !$data['start_all_day']
        ? $data['open_at'] : $current['open_at'];
      $fields = \core\diff($current,
        title: $data['title'], content: $data['content'], urgent: $data['urgent'],
        recurrence: $data['recurrence'], open_at: $open_at,
        due_at: $data['due_at'], due_all_day: $data['due_all_day'], status: $status);
      \store\update_task(
        $id, $data['title'], $data['content'], $data['urgent'], $data['recurrence'],
        $open_at, $data['due_at'], $data['due_all_day'], $current['expire_at']
      );
      $saved_collection = \caldav\task_collection($status);
      \store\set_task_status($id, $status);
    }
    else {
      $status = $data['status'] == 'COMPLETED' ? 'bought' : 'dream';
      $fields = \core\diff($current,
        title: $data['title'], content: $data['content'], urgent: $data['urgent'],
        added_at: $data['open_at'], status: $status);
      \store\update_wish($id, $data['title'], $data['content'], $data['urgent'], $data['open_at']);
      \store\set_wish_status($id, $status);
    }
  }

  save_properties($type, $id, $data);
  if($type == 'wish' && $data['wish_urls'] !== null) {
    \store\set_wish_urls($id, $data['wish_urls']);
    if(!$created) $fields = [...$fields, 'urls'];
  }
  if(!$created) $fields = [...$fields, 'properties', 'alarms'];
  $occupied = \store\get_caldav_resource_by_href($saved_collection, $saved_name);
  if($occupied && ($occupied['entity_type'] != $type || $occupied['entity_id'] != $id))
    $saved_name = $id . ".ics";
  \store\update_caldav_resource($type, $id, $saved_name, $saved_collection, uid: $data['uid']);
  \store\touch_caldav_resource($type, $id);
  $table = caldav_entity_table($type);
  $message = $created
    ? "Created $table/$id."
    : "Updated [" . join(", ", $fields) . "] for $table/$id.";
  \store\put_audit_log($table, $id, $message, 'caldav', operation: $created ? 'insert' : 'update');
  if($type == 'appointment') \caldav\mark_travel_changed($id);

  $changes = [];
  if($old_collection && ($old_collection != $saved_collection || $old_href != $saved_name))
    $changes[] = ['collection' => $old_collection, 'href' => $old_href, 'operation' => 'delete'];
  $changes[] = ['collection' => $saved_collection, 'href' => $saved_name, 'operation' => 'upsert'];
  \store\put_caldav_changes($changes);

  http_response_code($created ? 201 : 204);
  $saved = \store\get_caldav_resource_by_href($saved_collection, $saved_name);
  header('ETag: ' . \webdav\etag(\caldav\serialize($saved)));
  if($saved_collection != $collection['id'] || $saved_name != $name)
    header('Content-Location: ' . collection_href($saved_collection) . rawurlencode($saved_name));
  exit;
}

function delete_resource() {
  $location = locate();
  if($location[0] != 'resource' || !$location[3])
    dav_error(404, "CalDAV resource to delete was not found.");
  [$kind, $collection, $name, $resource] = $location;
  if($collection['readonly'])
    dav_error(403, "CalDAV collection '{$collection['id']}' is read-only.");
  precondition($resource);

  $row = \caldav\entity($resource);
  if($resource['entity_type'] == 'task' && @$row['status'] == 'done')
    dav_error(403, "Completed task {$resource['entity_id']} cannot be deleted through CalDAV.");

  $type = $resource['entity_type'];
  $id = $resource['entity_id'];
  if($type == 'appointment') {
    $operation = 'delete';
    \store\delete_appointment($id);
    \caldav\mark_resource_deleted($type, $id);
  }
  else {
    $operation = 'update';
    $type == 'task'
      ? \store\set_task_status($id, 'nvm')
      : \store\set_wish_status($id, 'nvm');
    \caldav\hide_resource($type, $id);
  }

  $table = caldav_entity_table($type);
  $message = $operation == 'delete'
    ? "Deleted $table/$id."
    : "Updated [status] for $table/$id.";
  \store\put_audit_log($table, $id, $message, 'caldav', operation: $operation);

  http_response_code(204); exit;
}

function move() {
  $location = locate();
  if($location[0] != 'resource' || !$location[3])
    dav_error(404, "CalDAV resource to move was not found.");
  [$kind, $source, $name, $resource] = $location;
  precondition($resource);
  $destination = @$_SERVER['HTTP_DESTINATION'] or dav_error(400, "MOVE requires a Destination header.");
  $destination_path = '/' . trim(parse_url($destination, PHP_URL_PATH), '/');
  $principal = preg_quote(CALDAV_PRINCIPAL, '@');
  if(!preg_match("@^/caldav/calendars/$principal/([^/]+)/([^/]+)$@", $destination_path, $match))
    dav_error(403, "MOVE destination '$destination_path' is not a CalDAV resource path.");
  $target_id = rawurldecode($match[1]);
  $target = \caldav\collection($target_id) or dav_error(404, "Destination collection '$target_id' was not found.");
  $target_name = rawurldecode($match[2]);
  if($source['readonly'] || $target['readonly'])
    dav_error(403, "Cannot move between '{$source['id']}' and '{$target['id']}': one is read-only.");
  if($target['type'] != $source['type'])
    dav_error(403, "Cannot move {$source['type']} resource '$name' into {$target['type']} collection '{$target['id']}'.");
  if($target['type'] == 'wish') dav_error(403, "Wishlist resources cannot move between collections.");
  if(\store\get_caldav_resource_by_href($target['id'], $target_name))
    dav_error(412, "Destination resource '{$target['id']}/$target_name' already exists.");

  $id = $resource['entity_id'];
  if($resource['entity_type'] == 'appointment') {
    $row = \caldav\entity($resource);
    $fields = \core\diff($row, calendar_id: $target['id']);
    \store\update_appointment(
      $id, $row['title'], $row['content'], $row['starts_at'], $row['ends_at'], $row['location'],
      $row['meeting'], $row['recurrence'], $row['all_day'], $row['going'], $row['urgent'],
      $row['travel_before'], $row['travel_after'], $target['id']
    );
  }
  else {
    $row = \caldav\entity($resource);
    $status = \caldav\task_status($target['id'], $row['status'] == 'done' ? 'COMPLETED' : 'NEEDS-ACTION', $row['status']);
    $fields = \core\diff($row, status: $status);
    \store\set_task_status($id, $status);
  }
  \store\touch_caldav_resource($resource['entity_type'], $id);
  $table = caldav_entity_table($resource['entity_type']);
  \store\put_audit_log($table, $id, "Updated [" . join(", ", $fields) . "] for $table/$id.", 'caldav');
  if($resource['entity_type'] == 'appointment') \caldav\mark_travel_changed($id);
  \store\update_caldav_resource($resource['entity_type'], $id, $target_name, $target['id']);
  \store\put_caldav_changes([
    ['collection' => $source['id'], 'href' => $name, 'operation' => 'delete'],
    ['collection' => $target['id'], 'href' => $target_name, 'operation' => 'upsert'],
  ]);

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
    if($element->namespaceURI == WEBDAV_XML_DAV && $element->localName == 'displayname')
      [$title, $subtitle] = \caldav\parse_displayname($element->textContent);
    elseif($element->namespaceURI == WEBDAV_XML_APPLE && $element->localName == 'calendar-color') $color = substr(trim($element->textContent), 0, 7);
    elseif($element->namespaceURI == WEBDAV_XML_APPLE && $element->localName == 'calendar-order') $position = (int)$element->textContent;
  }
  if(!$title) dav_error(409, "Calendar display name cannot be empty.");
  if(!preg_match('/^#[0-9a-fA-F]{6}$/', $color))
    dav_error(409, "Calendar color '$color' must use #RRGGBB format.");
  $fields = \core\diff($collection,
    title: $title, subtitle: $subtitle, color: strtolower($color), position: $position);
  \store\update_calendar(
    $collection['id'], $title, $subtitle, strtolower($color), position: $position
  );
  \store\put_audit_log('calendars', $collection['id'],
    "Updated [" . join(", ", $fields) . "] for calendars/{$collection['id']}.", 'caldav');
  multistatus([response(collection_href($collection['id']), collection_properties(\caldav\collection($collection['id'])), null)]);
}

function get_resource($head = false) {
  $location = locate();
  if($location[0] != 'resource' || !$location[3])
    dav_error(404, "Requested CalDAV resource was not found.");
  [$kind, $collection, $name, $resource] = $location;
  $body = \caldav\serialize($resource);
  $etag = \webdav\etag($body);
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
  default => dav_error(405, "HTTP method {$_SERVER['REQUEST_METHOD']} is not supported by CalDAV."),
};
