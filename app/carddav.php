<?php
// CardDAV server for contacts and organisations.
// Lovingly written by Claude.

use function webdav\href, webdav\text, webdav\xml_body, webdav\request_properties,
  webdav\response, webdav\multistatus;

\carddav\reconcile();

function collection_href($id) {
  return '/carddav/addressbooks/' . CARDDAV_PRINCIPAL . '/' . rawurlencode($id) . '/';
}

function collection_properties($collection) {
  $revision = \store\carddav_collection_revision($collection['id']);
  return [
    WEBDAV_XML_DAV . '|resourcetype' => ['raw' => '<D:collection/><CARD:addressbook/>'],
    WEBDAV_XML_DAV . '|displayname' => ['text' => $collection['displayname']],
    WEBDAV_XML_CARDDAV . '|addressbook-description' => ['text' => $collection['description']],
    WEBDAV_XML_CARDDAV . '|supported-address-data' => ['raw' =>
      '<CARD:address-data-type content-type="text/vcard" version="3.0"/>'
      . '<CARD:address-data-type content-type="text/vcard" version="4.0"/>'],
    WEBDAV_XML_DAV . '|sync-token' => ['text' => \webdav\sync_token('carddav', $collection['id'], $revision)],
    WEBDAV_XML_DAV . '|supported-report-set' => ['raw' => '<D:supported-report><D:report><CARD:addressbook-query/></D:report></D:supported-report>'
      . '<D:supported-report><D:report><CARD:addressbook-multiget/></D:report></D:supported-report>'
      . '<D:supported-report><D:report><D:sync-collection/></D:report></D:supported-report>'],
    WEBDAV_XML_SERVER . '|getctag' => ['text' => (string)$revision],
    WEBDAV_XML_DAV . '|current-user-privilege-set' => ['raw' =>
      '<D:privilege><D:read/></D:privilege><D:privilege><D:write-content/></D:privilege>'],
  ];
}

function resource_properties($collection, $resource, $address_data = false) {
  $body = \carddav\serialize($resource);
  if($body === null) return null;
  $properties = [
    WEBDAV_XML_DAV . '|resourcetype' => ['text' => ''],
    WEBDAV_XML_DAV . '|getetag' => ['text' => \webdav\etag($body)],
    WEBDAV_XML_DAV . '|getcontenttype' => ['text' => 'text/vcard; charset=utf-8'],
    WEBDAV_XML_DAV . '|getcontentlength' => ['text' => (string)strlen($body)],
    WEBDAV_XML_DAV . '|getlastmodified' => ['text' => gmdate('D, d M Y H:i:s', strtotime($resource['touched_at'])) . ' GMT'],
  ];
  if($address_data) $properties[WEBDAV_XML_CARDDAV . '|address-data'] = ['text' => $body];
  return $properties;
}

function locate() {
  global $path;
  $principal = preg_quote(CARDDAV_PRINCIPAL, '@');

  if($path == '/carddav') return ['root'];
  if($path == '/carddav/principals/' . CARDDAV_PRINCIPAL) return ['principal'];
  if($path == '/carddav/addressbooks/' . CARDDAV_PRINCIPAL) return ['home'];
  if(preg_match("@^/carddav/addressbooks/$principal/([^/]+)(?:/(.+))?$@", $path, $match)) {
    $id = rawurldecode($match[1]);
    $collection = \carddav\collection($id) or dav_error(404, "CardDAV address book '$id' was not found.");
    if(!isset($match[2])) return ['collection', $collection];
    $href = rawurldecode($match[2]);
    if(str_contains($href, '/')) dav_error(404, "Invalid CardDAV resource path '$href'.");
    return ['resource', $collection, $href, \store\get_carddav_resource_by_href($id, $href)];
  }
  dav_error(404, "CardDAV path '$path' was not found.");
}

function propfind() {
  $depth = @$_SERVER['HTTP_DEPTH'] ?: '0';
  if(!in_array($depth, ['0', '1'])) dav_error(403, "PROPFIND Depth '$depth' is unsupported; use 0 or 1.");
  $requested = request_properties(xml_body());
  $location = locate();
  $responses = [];

  $principal_properties = \webdav\principal_properties('carddav',
    WEBDAV_XML_CARDDAV . '|addressbook-home-set', '/carddav/addressbooks/' . CARDDAV_PRINCIPAL . '/');

  if($location[0] == 'root') {
    $responses[] = response('/carddav/', \webdav\root_properties('carddav'), $requested);
    if($depth == '1') $responses[] = response('/carddav/principals/' . CARDDAV_PRINCIPAL . '/', $principal_properties, $requested);
  }
  elseif($location[0] == 'principal') {
    $responses[] = response('/carddav/principals/' . CARDDAV_PRINCIPAL . '/', $principal_properties, $requested);
  }
  elseif($location[0] == 'home') {
    $responses[] = response('/carddav/addressbooks/' . CARDDAV_PRINCIPAL . '/', \webdav\home_properties('carddav', 'Everything contacts'), $requested);
    if($depth == '1') foreach(\carddav\collections() as $collection)
      $responses[] = response(collection_href($collection['id']), collection_properties($collection), $requested);
  }
  elseif($location[0] == 'collection') {
    $collection = $location[1];
    $responses[] = response(collection_href($collection['id']), collection_properties($collection), $requested);
    if($depth == '1') foreach(\store\list_carddav_resources_by_collection($collection['id']) as $resource)
      $responses[] = response(collection_href($collection['id']) . rawurlencode($resource['href']), resource_properties($collection, $resource), $requested);
  }
  else {
    [$kind, $collection, $name, $resource] = $location;
    if(!$resource) dav_error(404, "CardDAV resource '$name' was not found in address book '{$collection['id']}'.");
    $responses[] = response(collection_href($collection['id']) . rawurlencode($name), resource_properties($collection, $resource), $requested);
  }

  multistatus($responses);
}

function report() {
  $location = locate();
  if($location[0] != 'collection')
    dav_error(403, "REPORT path must name an address book; received {$location[0]}.");
  $collection = $location[1];
  $document = xml_body() or dav_error(400, "REPORT body is required.");
  $report = $document->documentElement->localName;
  $requested = request_properties($document);
  $responses = [];

  if($report == 'addressbook-multiget') {
    foreach($document->getElementsByTagNameNS(WEBDAV_XML_DAV, 'href') as $node) {
      $name = rawurldecode(basename(parse_url($node->textContent, PHP_URL_PATH)));
      $resource = \store\get_carddav_resource_by_href($collection['id'], $name);
      $url = collection_href($collection['id']) . rawurlencode($name);
      $responses[] = $resource
        ? response($url, resource_properties($collection, $resource, address_data: true), $requested)
        : response($url, [], $requested, 404);
    }
  }
  elseif($report == 'addressbook-query') {
    foreach(['prop-filter', 'param-filter', 'text-match', 'is-not-defined', 'limit'] as $filter)
      if($document->getElementsByTagNameNS(WEBDAV_XML_CARDDAV, $filter)->length)
        \logger\warn("Ignored unsupported CardDAV address book filter $filter.");

    foreach(\store\list_carddav_resources_by_collection($collection['id']) as $resource)
      $responses[] = response(collection_href($collection['id']) . rawurlencode($resource['href']),
        resource_properties($collection, $resource, address_data: true), $requested);
  }
  elseif($report == 'sync-collection') {
    $tokens = $document->getElementsByTagNameNS(WEBDAV_XML_DAV, 'sync-token');
    $token = $tokens->length ? trim($tokens->item(0)->textContent) : '';
    $revision = \webdav\sync_revision($token, $collection['id']);
    if($revision === false)
      dav_error(403, "Sync token '$token' is invalid for address book '{$collection['id']}'.", 'D:valid-sync-token');

    if($revision === null) {
      foreach(\store\list_carddav_resources_by_collection($collection['id']) as $resource)
        $responses[] = response(collection_href($collection['id']) . rawurlencode($resource['href']),
          resource_properties($collection, $resource, address_data: true), $requested);
    }
    else {
      if($revision > \store\carddav_global_revision())
        dav_error(403, "Sync revision $revision is newer than the server revision " . \store\carddav_global_revision() . ".", 'D:valid-sync-token');
      foreach(\store\list_carddav_changes($collection['id'], $revision) as $change) {
        $url = collection_href($collection['id']) . rawurlencode($change['href']);
        $resource = $change['operation'] == 'upsert'
          ? \store\get_carddav_resource_by_href($collection['id'], $change['href']) : null;
        $responses[] = $resource
          ? response($url, resource_properties($collection, $resource, address_data: true), $requested)
          : response($url, [], $requested, 404);
      }
    }

    $current = \store\carddav_collection_revision($collection['id']);
    multistatus($responses,
      '<D:sync-token>' . text(\webdav\sync_token('carddav', $collection['id'], $current)) . '</D:sync-token>');
  }
  else {
    dav_error(403, "CardDAV REPORT '$report' is unsupported.", 'D:supported-report');
  }

  multistatus($responses);
}

function precondition($resource) {
  \webdav\precondition($resource ? \webdav\etag(\carddav\serialize($resource)) : null);
}

function expected_kind($collection, $card) {
  $kind = \carddav\card_kind($card);
  if($collection['id'] == 'organisations') {
    if($kind == 'tag') dav_error(403, "Group cards belong in the contacts address book.", 'valid-address-data');
    return 'organisation';
  }
  if($kind == 'organisation')
    dav_error(403, "Organisation cards belong in the organisations address book.", 'valid-address-data');
  return $kind;
}

function apply_contact($id, $data) {
  $f = $data['fields'];
  $creating = !$id;

  if($creating) {
    $id = \store\put_contact(
      $f['display_name'], $f['first_name'], $f['middle_name'],
      $f['legal_infix'], $f['legal_name'], $f['family_infix'], $f['family_name'], $f['name_order'],
      $f['nickname'], $f['pronouns'],
      $f['birth_day'], $f['birth_month'], $f['birth_year'],
      $f['anniversary_day'], $f['anniversary_month'], $f['anniversary_year'],
      $f['timezone'], $f['note']
    );
  }
  else {
    \store\update_contact(
      $id,
      $f['display_name'], $f['first_name'], $f['middle_name'],
      $f['legal_infix'], $f['legal_name'], $f['family_infix'], $f['family_name'], $f['name_order'],
      $f['nickname'], $f['pronouns'],
      $f['birth_day'], $f['birth_month'], $f['birth_year'],
      $f['anniversary_day'], $f['anniversary_month'], $f['anniversary_year'],
      $f['timezone'], $f['note']
    );
  }

  \store\set_contact_emails($id, $data['emails']);
  \store\set_contact_phone_numbers($id, $data['phones']);
  \store\set_contact_urls($id, $data['urls']);
  \store\set_contact_socials($id, $data['socials']);
  \store\set_contact_roles($id, $data['roles']);
  \store\set_contact_addresses($id, $data['addresses']);
  if($data['tags'] !== null) \store\set_contact_tags($id, $data['tags']);
  \store\replace_properties('contact', $id, $data['properties']);

  return $id;
}

function apply_organisation($id, $data) {
  $f = $data['fields'];
  $creating = !$id;

  if($creating) {
    $id = \store\put_organisation(
      $f['display_name'], $f['legal_name'], $f['registration_number'], $f['vat_number'],
      $f['timezone'], $f['note']
    );
  }
  else {
    \store\update_organisation(
      $id, $f['display_name'], $f['legal_name'], $f['registration_number'], $f['vat_number'],
      $f['timezone'], $f['note']
    );
  }

  \store\set_organisation_emails($id, $data['emails']);
  \store\set_organisation_phone_numbers($id, $data['phones']);
  \store\set_organisation_urls($id, $data['urls']);
  \store\set_organisation_socials($id, $data['socials']);
  \store\set_organisation_addresses($id, $data['addresses']);
  if($data['tags'] !== null) \store\set_organisation_tags($id, $data['tags']);
  \store\replace_properties('organisation', $id, $data['properties']);

  return $id;
}

function put() {
  $location = locate();
  if($location[0] != 'resource') dav_error(405, "PUT path must name a resource inside an address book.");
  [$kind, $collection, $name, $resource] = $location;
  precondition($resource);

  try { $card = \carddav\parse(file_get_contents('php://input')); }
  catch(\InvalidArgumentException $e) { dav_error(403, $e->getMessage(), 'valid-address-data'); }

  $uid = \carddav\card_uid($card);
  $type = expected_kind($collection, $card);

  $by_uid = \carddav\resource_by_uid($uid);
  if($by_uid && $by_uid['collection'] != $collection['id'])
    dav_error(409, "UID '$uid' belongs to the '{$by_uid['collection']}' address book.");
  if($resource && $resource['uid'] != $uid)
    dav_error(409, "Resource '$name' has UID '{$resource['uid']}'; it cannot be changed to '$uid'.");
  if($resource && $by_uid && ($by_uid['entity_type'] != $resource['entity_type'] || $by_uid['entity_id'] != $resource['entity_id']))
    dav_error(409, "UID '$uid' already belongs to resource '{$by_uid['href']}'.");
  if(!$resource && $by_uid) $resource = $by_uid;

  if($type == 'tag' || ($resource && $resource['entity_type'] == 'tag')) { put_group($collection, $name, $resource, $card, $type); return; }
  if($resource && $resource['entity_type'] != $type)
    dav_error(403, "Resource type {$resource['entity_type']} cannot be replaced by a $type card.");

  $created = !$resource;
  $id = $resource ? $resource['entity_id'] : null;
  $table = $type == 'contact' ? 'contacts' : 'organisations';

  try {
    if($type == 'contact') {
      $current = $id ? \store\get_contact($id) : null;
      if($id && !$current) dav_error(404, "Contact $id no longer exists.");
      $data = \carddav\parse_contact($card, $current);
      $fields = $current ? \core\diff($current, ...$data['fields']) : [];
      $id = apply_contact($id, $data);
    }
    else {
      $current = $id ? \store\get_organisation($id) : null;
      if($id && !$current) dav_error(404, "Organisation $id no longer exists.");
      $data = \carddav\parse_organisation($card, $current);
      $fields = $current ? \core\diff($current, ...$data['fields']) : [];
      $id = apply_organisation($id, $data);
    }
  }
  catch(\InvalidArgumentException $e) { dav_error(403, $e->getMessage(), 'valid-address-data'); }

  $saved_name = $name;
  $occupied = \store\get_carddav_resource_by_href($collection['id'], $saved_name);
  if($occupied && ($occupied['entity_type'] != $type || $occupied['entity_id'] != $id))
    $saved_name = preg_replace('/^urn:uuid:/i', '', $uid) . ".vcf";
  \store\update_carddav_resource($type, $id, $saved_name, $collection['id'], uid: $uid);
  \carddav\forget();
  $saved = \store\get_carddav_resource($type, $id);
  \store\touch_carddav_resource($type, $id, \carddav\fingerprint(\carddav\entity($saved)));

  $message = $created
    ? "Created $table/$id."
    : "Updated [" . join(", ", [...$fields, 'children', 'properties']) . "] for $table/$id.";
  \store\put_audit_log($table, $id, $message, 'carddav', operation: $created ? 'insert' : 'update');

  $changes = [];
  if($resource && $resource['href'] != $saved_name)
    $changes[] = ['collection' => $collection['id'], 'href' => $resource['href'], 'operation' => 'delete'];
  $changes[] = ['collection' => $collection['id'], 'href' => $saved_name, 'operation' => 'upsert'];
  \store\put_carddav_changes($changes);

  http_response_code($created ? 201 : 204);
  $saved = \store\get_carddav_resource($type, $id);
  header('ETag: ' . \webdav\etag(\carddav\serialize($saved)));
  if($saved_name != $name)
    header('Content-Location: ' . collection_href($collection['id']) . rawurlencode($saved_name));
  exit;
}

function put_group($collection, $name, $resource, $card, $kind) {
  if(!$resource || $resource['entity_type'] != 'tag')
    dav_error(403, "Tag groups cannot be created through CardDAV.");
  if($kind != 'tag')
    dav_error(403, "Resource '{$resource['href']}' is a tag group; only group cards can replace it.", 'valid-address-data');

  $id = $resource['entity_id'];
  $tag = \store\get_tag($id) or dav_error(404, "Tag $id no longer exists.");

  try { $data = \carddav\parse_group($card, $tag); }
  catch(\InvalidArgumentException $e) { dav_error(403, $e->getMessage(), 'valid-address-data'); }
  if($data['unknown'])
    dav_error(409, "Unknown group members: " . join(", ", $data['unknown']) . ".");

  \store\set_tag_members($id, $data['member_ids']);
  \store\replace_properties('tag', $id, $data['properties']);
  \store\put_audit_log('tags', $id, "Updated [members] for tags/$id.", 'carddav');

  \carddav\forget();
  \store\touch_carddav_resource('tag', $id, \carddav\fingerprint(\carddav\entity($resource)));
  \store\put_carddav_changes([[
    'collection' => $collection['id'], 'href' => $resource['href'], 'operation' => 'upsert',
  ]]);

  http_response_code(204);
  $saved = \store\get_carddav_resource('tag', $id);
  header('ETag: ' . \webdav\etag(\carddav\serialize($saved)));
  exit;
}

function delete_resource() {
  $location = locate();
  if($location[0] != 'resource' || !$location[3])
    dav_error(404, "CardDAV resource to delete was not found.");
  [$kind, $collection, $name, $resource] = $location;
  precondition($resource);

  if($resource['entity_type'] == 'tag')
    dav_error(403, "Tag groups cannot be deleted through CardDAV.");

  $type = $resource['entity_type'];
  $id = $resource['entity_id'];
  $table = $type == 'contact' ? 'contacts' : 'organisations';
  $type == 'contact' ? \store\delete_contact($id) : \store\delete_organisation($id);
  \store\put_audit_log($table, $id, "Deleted $table/$id.", 'carddav', operation: 'delete');

  \store\delete_carddav_resource($type, $id);
  \store\put_carddav_changes([[
    'collection' => $collection['id'], 'href' => $name, 'operation' => 'delete',
  ]]);

  http_response_code(204); exit;
}

function move() {
  $location = locate();
  if($location[0] != 'resource' || !$location[3])
    dav_error(404, "CardDAV resource to move was not found.");
  [$kind, $source, $name, $resource] = $location;
  precondition($resource);
  $destination = @$_SERVER['HTTP_DESTINATION'] or dav_error(400, "MOVE requires a Destination header.");
  $destination_path = '/' . trim(parse_url($destination, PHP_URL_PATH), '/');
  $principal = preg_quote(CARDDAV_PRINCIPAL, '@');
  if(!preg_match("@^/carddav/addressbooks/$principal/([^/]+)/([^/]+)$@", $destination_path, $match))
    dav_error(403, "MOVE destination '$destination_path' is not a CardDAV resource path.");
  $target_id = rawurldecode($match[1]);
  $target_name = rawurldecode($match[2]);
  \carddav\collection($target_id) or dav_error(404, "Destination address book '$target_id' was not found.");
  if($target_id != $source['id'])
    dav_error(403, "CardDAV resources cannot move between address books.");
  if(\store\get_carddav_resource_by_href($target_id, $target_name))
    dav_error(412, "Destination resource '$target_id/$target_name' already exists.");

  \store\update_carddav_resource($resource['entity_type'], $resource['entity_id'], $target_name, $source['id']);
  \store\put_carddav_changes([
    ['collection' => $source['id'], 'href' => $name, 'operation' => 'delete'],
    ['collection' => $source['id'], 'href' => $target_name, 'operation' => 'upsert'],
  ]);

  http_response_code(201); exit;
}

function get_resource($head = false) {
  $location = locate();
  if($location[0] != 'resource' || !$location[3])
    dav_error(404, "Requested CardDAV resource was not found.");
  [$kind, $collection, $name, $resource] = $location;
  $body = \carddav\serialize($resource);
  $etag = \webdav\etag($body);
  if(@$_SERVER['HTTP_IF_NONE_MATCH'] == $etag) { http_response_code(304); exit; }
  header("Content-Type: text/vcard; charset=utf-8");
  header("Content-Length: " . strlen($body));
  header("ETag: $etag");
  if(!$head) echo $body;
  exit;
}

function options() {
  header("Allow: OPTIONS, PROPFIND, REPORT, GET, HEAD, PUT, DELETE, MOVE");
  header("DAV: 1, 3, addressbook, sync-collection");
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
  'OPTIONS' => options(),
  default => dav_error(405, "HTTP method {$_SERVER['REQUEST_METHOD']} is not supported by CardDAV."),
};
