<?php
// CardDAV helpers: vCard 3.0 serialization, vCard 3.0/4.0 parsing and
// fingerprint-based reconciliation for contacts, organisations and tag
// groups. Written by Claude, like its CalDAV sibling.

namespace carddav;

use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

define('CARDDAV_PRINCIPAL', 'everything');

// Standard label <-> TYPE mappings. Custom labels round-trip through
// grouped X-ABLabel properties instead.
define('CARDDAV_TEL_TYPES', [
  'mobile' => 'CELL',
  'home' => 'HOME',
  'work' => 'WORK',
  'fax' => 'FAX',
  'pager' => 'PAGER',
  'main' => 'MAIN',
  'iphone' => 'IPHONE',
  'other' => 'OTHER',
]);

define('CARDDAV_GENERIC_TYPES', [
  'home' => 'HOME',
  'work' => 'WORK',
  'other' => 'OTHER',
]);

define('CARDDAV_APPLE_LABELS', [
  '_$!<Home>!$_' => 'home',
  '_$!<Work>!$_' => 'work',
  '_$!<Mobile>!$_' => 'mobile',
  '_$!<Main>!$_' => 'main',
  '_$!<Other>!$_' => 'other',
  '_$!<HomePage>!$_' => 'homepage',
  '_$!<School>!$_' => 'school',
  '_$!<Pager>!$_' => 'pager',
  '_$!<Anniversary>!$_' => 'anniversary',
]);

// N honorifics have no model field; they live as property rows that the
// serializer folds back into the N components instead of emitting as lines.
define('CARDDAV_N_PARTS', ['X-EVERYTHING-N-PREFIX', 'X-EVERYTHING-N-SUFFIX']);

function collections() {
  return [
    'contacts' => [
      'id' => 'contacts',
      'displayname' => "Contacts",
      'description' => "People and groups",
    ],
    'organisations' => [
      'id' => 'organisations',
      'displayname' => "Organisations",
      'description' => "Companies and institutions",
    ],
  ];
}

function collection($id) {
  return @collections()[$id];
}

// The book: every contact, organisation and tag with children, loaded in
// bulk once per request. Reconciliation fingerprints all of it and the
// serializer reads from it.

$_BOOK = null;

function book() {
  global $_BOOK;
  if($_BOOK !== null) return $_BOOK;

  $group = function($rows, $key) {
    $result = [];
    foreach($rows as $row) $result[$row[$key]][] = $row;
    return $result;
  };

  $contacts = [];
  foreach(\store\list_contact_rows() as $row) $contacts[$row['id']] = $row + [
    'emails' => [], 'phone_numbers' => [], 'urls' => [], 'socials' => [],
    'roles' => [], 'addresses' => [], 'tags' => [], 'properties' => [],
    'picture' => null,
  ];
  foreach([
    'emails' => \store\list_all_contact_emails(),
    'phone_numbers' => \store\list_all_contact_phone_numbers(),
    'urls' => \store\list_all_contact_urls(),
    'socials' => \store\list_all_contact_socials(),
    'roles' => \store\list_all_contact_roles(),
    'addresses' => \store\list_all_contact_addresses(),
    'tags' => \store\list_all_contact_tags(),
    'properties' => \store\list_all_properties('contact'),
  ] as $field => $rows) {
    foreach($group($rows, 'contact_id') as $id => $children)
      if(isset($contacts[$id])) $contacts[$id][$field] = $children;
  }

  $organisations = [];
  foreach(\store\list_organisation_rows() as $row) $organisations[$row['id']] = $row + [
    'emails' => [], 'phone_numbers' => [], 'urls' => [], 'socials' => [],
    'addresses' => [], 'tags' => [], 'properties' => [],
    'picture' => null,
  ];
  foreach([
    'emails' => \store\list_all_org_emails(),
    'phone_numbers' => \store\list_all_org_phone_numbers(),
    'urls' => \store\list_all_org_urls(),
    'socials' => \store\list_all_org_socials(),
    'addresses' => \store\list_all_org_addresses(),
    'tags' => \store\list_all_org_tags(),
    'properties' => \store\list_all_properties('organisation'),
  ] as $field => $rows) {
    foreach($group($rows, 'org_id') as $id => $children)
      if(isset($organisations[$id])) $organisations[$id][$field] = $children;
  }

  foreach(\store\list_profile_picture_metadata() as $picture) {
    if($picture['contact_id'] && isset($contacts[$picture['contact_id']]))
      $contacts[$picture['contact_id']]['picture'] = $picture;
    if($picture['org_id'] && isset($organisations[$picture['org_id']]))
      $organisations[$picture['org_id']]['picture'] = $picture;
  }

  $tags = [];
  foreach(\store\list_tag_rows() as $row) $tags[$row['id']] = $row + [
    'members' => [], 'properties' => [],
  ];
  foreach($contacts as $id => $contact)
    foreach($contact['tags'] as $tag)
      if(isset($tags[$tag['id']])) $tags[$tag['id']]['members'][] = $id;
  foreach($group(\store\list_all_properties('tag'), 'tag_id') as $id => $children)
    if(isset($tags[$id])) $tags[$id]['properties'] = $children;

  return $_BOOK = [
    'contacts' => $contacts,
    'organisations' => $organisations,
    'tags' => $tags,
  ];
}

function forget() {
  global $_BOOK;
  $_BOOK = null;
}

function entity($resource) {
  $shelf = match($resource['entity_type']) {
    'contact' => 'contacts',
    'organisation' => 'organisations',
    'tag' => 'tags',
  };
  return @book()[$shelf][$resource['entity_id']];
}

// Content lines

function line($name, $value, $parameters = [], $group = null) {
  $line = ($group ? $group . '.' : '') . strtoupper($name);
  foreach($parameters as $param)
    $line .= \caldav\parameter($param['name'], $param['values']);
  return \caldav\fold($line . ':' . $value);
}

function escape($value) {
  return \icalendar\escape_text((string)$value);
}

function type_params($types) {
  return array_map(fn($type) => ['name' => 'TYPE', 'values' => [$type]], $types);
}

function date_value($day, $month, $year) {
  if($year) return [sprintf('%04d-%02d-%02d', $year, $month, $day), []];
  return [sprintf('1604-%02d-%02d', $month, $day),
    [['name' => 'X-APPLE-OMIT-YEAR', 'values' => ['1604']]]];
}

function country_name($code) {
  if(!$code) return null;
  if(class_exists('Locale')) {
    $name = \Locale::getDisplayRegion("-$code", 'en');
    if($name && $name != $code) return $name;
  }
  return $code;
}

function country_code($value) {
  $value = trim((string)$value);
  if($value == "") return null;
  if(preg_match('/^[A-Za-z]{2}$/', $value) && in_array(strtoupper($value), \country_codes()))
    return strtoupper($value);

  static $names = null;
  if($names === null) {
    $names = [];
    if(class_exists('Locale')) foreach(\country_codes() as $code)
      $names[str_normalize(\Locale::getDisplayRegion("-$code", 'en'))] = $code;
  }
  return @$names[str_normalize($value)];
}

// Serialization. Cards are vCard 3.0: the oldest clients in the target
// matrix (Snow Leopard, iOS 6, Windows Thunderbird) predate 4.0 support.

function serialize($resource) {
  $row = entity($resource);
  if(!$row) return null;

  return match($resource['entity_type']) {
    'contact' => serialize_contact($resource, $row),
    'organisation' => serialize_organisation($resource, $row),
    'tag' => serialize_tag($resource, $row),
  };
}

function rev($resource) {
  return (new \DateTimeImmutable($resource['touched_at']))
    ->setTimezone(new \DateTimeZone("UTC"))->format('Ymd\THis\Z');
}

function header_lines($resource, $fn, $n_parts) {
  $body = "BEGIN:VCARD\r\n";
  $body .= line('VERSION', '3.0');
  $body .= line('PRODID', '-//Everything//CardDAV//EN');
  $body .= line('UID', escape($resource['uid']));
  $body .= line('REV', rev($resource));
  $body .= line('N', implode(';', array_map(fn($part) => escape($part), $n_parts)));
  $body .= line('FN', escape($fn));
  return $body;
}

function n_extras($properties) {
  $extras = ['X-EVERYTHING-N-PREFIX' => "", 'X-EVERYTHING-N-SUFFIX' => ""];
  foreach($properties as $property)
    if(isset($extras[$property['name']])) $extras[$property['name']] = $property['value'];
  return array_values($extras);
}

// Emits one child list (emails, phones, urls). Standard labels become TYPE
// parameters; custom labels become a grouped X-ABLabel. $groups tracks the
// deterministic evrN group counter for the whole card.
function child_lines($rows, $name, $value_of, $label_of, $types, $base_params, &$groups) {
  $body = "";
  foreach($rows as $row) {
    $label = trim((string)$label_of($row));
    $type = @$types[strtolower($label)];
    $params = $base_params;
    if($type) $params = [...$params, ...type_params([$type])];

    if($label == "" || $type) {
      $body .= line($name, $value_of($row), $params);
      continue;
    }

    $group = 'evr' . ++$groups;
    $body .= line($name, $value_of($row), $params, $group);
    $body .= line('X-ABLabel', label_out($label), [], $group);
  }
  return $body;
}

// Labels that came in as Apple builtins go back out in builtin form, so
// Apple clients keep showing their localized label.
function label_out($label) {
  $apple = array_search(strtolower($label), CARDDAV_APPLE_LABELS);
  return $apple !== false ? $apple : escape($label);
}

function address_lines($rows, &$groups) {
  $body = "";
  foreach($rows as $row) {
    $label = trim((string)@$row['link_label']);
    $type = @CARDDAV_GENERIC_TYPES[strtolower($label)];
    $group = 'evr' . ++$groups;

    $value = implode(';', [
      "", "",
      escape($row['street_address']),
      escape($row['city']),
      escape($row['province']),
      escape($row['postal_code']),
      escape(country_name($row['country'])),
    ]);
    $body .= line('ADR', $value, $type ? type_params([$type]) : [], $group);
    if($label != "" && !$type) $body .= line('X-ABLabel', label_out($label), [], $group);
    if($row['country']) $body .= line('X-ABADR', strtolower($row['country']), [], $group);
    $body .= line('X-EVERYTHING-ADDRESS', (string)$row['id'], [], $group);
  }
  return $body;
}

// Services without a public profile URL get Apple's x-apple: URI as the
// value; a bare handle there renders as username:value garbage in Contacts.
function social_lines($rows, $kind) {
  $body = "";
  foreach($rows as $row) {
    $url = \contacts\social_url($row['type'], $row['handle'], $kind);
    $params = [
      ['name' => 'TYPE', 'values' => [\contacts\social_label($row['type'])]],
      ['name' => 'X-USER', 'values' => [$row['handle']]],
    ];
    $body .= line('X-SOCIALPROFILE', escape($url ?: 'x-apple:' . $row['handle']), $params);
    if($row['type'] == 'matrix')
      $body .= line('IMPP', 'matrix:' . escape($row['handle']), [
        ['name' => 'X-SERVICE-TYPE', 'values' => ['matrix']],
      ]);
  }
  return $body;
}

function date_lines($name, $aliases, $day, $month, $year, $apple_label, &$groups) {
  if(!$day || !$month) return "";
  [$value, $params] = date_value($day, $month, $year);
  $body = line($name, $value, $params);
  foreach($aliases as $alias) $body .= line($alias, $value, $params);
  if($apple_label) {
    $group = 'evr' . ++$groups;
    $body .= line('X-ABDATE', $value, $params, $group);
    $body .= line('X-ABLabel', $apple_label, [], $group);
  }
  return $body;
}

function categories_line($tags) {
  if(!$tags) return "";
  $labels = array_map(fn($tag) => escape($tag['label']), $tags);
  return line('CATEGORIES', implode(',', $labels));
}

function picture_line($type, $id, $picture) {
  if(!$picture) return "";
  $stored = \store\get_profile_picture($type, $id);
  if(!$stored) return "";
  $format = strtoupper($stored['mime_type'] == 'image/jpeg' ? 'JPEG' : substr($stored['mime_type'], 6));
  return line('PHOTO', base64_encode($stored['content']), [
    ['name' => 'ENCODING', 'values' => ['b']],
    ['name' => 'TYPE', 'values' => [$format]],
  ]);
}

function retained_lines($properties, $skip = []) {
  $body = "";
  foreach($properties as $property) {
    if(in_array($property['name'], [...CARDDAV_N_PARTS, ...$skip])) continue;
    $params = json_decode($property['parameters'], true) ?: [];
    $body .= line($property['name'], $property['value'], $params, @$property['group_name']);
  }
  return $body;
}

function main_role($roles) {
  foreach($roles as $role) if(\cast_bool($role['main'])) return $role;
  return @$roles[0];
}

function serialize_contact($resource, $row) {
  $groups = 0;
  $surname = \contacts\contact_display_surname($row);
  // NICKNAME is transmitted separately; clients apply their own nickname
  // display preference. Apple clients derive the visible name from N, so a
  // display_name override must be represented there as well as in FN.
  $fn = \contacts\contact_display_name([...$row, 'nickname' => null]);
  $name = is_str($row['display_name'])
    ? ["", $fn, ""]
    : [$surname, $row['first_name'], $row['middle_name']];
  $body = header_lines($resource, $fn,
    [...$name, ...n_extras($row['properties'])]);

  $body .= picture_line('contact', $row['id'], $row['picture']);
  if(is_str($row['nickname'])) $body .= line('NICKNAME', escape($row['nickname']));
  if(is_str($row['pronouns'])) {
    $body .= line('PRONOUNS', escape($row['pronouns']));
    $body .= line('X-PRONOUNS', escape($row['pronouns']));
  }

  $body .= date_lines('BDAY', [], $row['birth_day'], $row['birth_month'], $row['birth_year'], null, $groups);
  $body .= date_lines('ANNIVERSARY', ['X-ANNIVERSARY'],
    $row['anniversary_day'], $row['anniversary_month'], $row['anniversary_year'],
    '_$!<Anniversary>!$_', $groups);

  $internet = [['name' => 'TYPE', 'values' => ['INTERNET']]];
  $body .= child_lines($row['emails'], 'EMAIL',
    fn($r) => escape($r['email']), fn($r) => @$r['label'], CARDDAV_GENERIC_TYPES, $internet, $groups);
  $body .= child_lines($row['phone_numbers'], 'TEL',
    fn($r) => escape($r['phone_number']), fn($r) => @$r['label'], CARDDAV_TEL_TYPES, [], $groups);
  $body .= address_lines($row['addresses'], $groups);
  $body .= child_lines($row['urls'], 'URL',
    fn($r) => escape($r['url']), fn($r) => @$r['label'], CARDDAV_GENERIC_TYPES, [], $groups);
  $body .= social_lines($row['socials'], 'person');

  if($row['timezone']) $body .= line('TZ', escape($row['timezone']), [['name' => 'VALUE', 'values' => ['TEXT']]]);
  $body .= categories_line($row['tags']);

  if($main = main_role($row['roles'])) {
    $body .= line('ORG', escape($main['organisation_name']) . ';');
    if(is_str($main['role'])) $body .= line('TITLE', escape($main['role']));
  }
  foreach($row['roles'] as $role) {
    $organisation = \store\get_carddav_resource('organisation', $role['org_id']);
    if($organisation) $body .= line('X-EVERYTHING-ROLE', implode(';', [
      escape($organisation['uid']),
      cast_bool($role['main']) ? '1' : '0',
      escape($role['role']),
    ]));
  }

  if(needs_name_structure($row))
    $body .= line('X-EVERYTHING-NAME', implode(';', [
      escape($row['family_infix']), escape($row['family_name']),
      escape($row['legal_infix']), escape($row['legal_name']),
      $row['name_order'],
    ]));

  if(is_str($row['note'])) $body .= line('NOTE', escape($row['note']));
  $body .= line('X-EVERYTHING-SCHEMA', '1');
  $body .= retained_lines($row['properties']);
  return $body . "END:VCARD\r\n";
}

// N only carries the combined surname, so the split into family and legal
// parts needs a private property whenever more than a plain family name
// is involved.
function needs_name_structure($row) {
  return is_str($row['family_infix'])
    || is_str($row['legal_infix'])
    || is_str($row['legal_name'])
    || $row['name_order'] != 'family_legal';
}

function serialize_organisation($resource, $row) {
  $groups = 0;
  $body = header_lines($resource, $row['display_name'],
    [$row['display_name'], "", "", ...n_extras($row['properties'])]);
  $body .= line('ORG', escape($row['display_name']) . ';');
  $body .= line('KIND', 'org');
  $body .= line('X-ABShowAs', 'COMPANY');
  $body .= picture_line('organisation', $row['id'], $row['picture']);

  $internet = [['name' => 'TYPE', 'values' => ['INTERNET']]];
  $body .= child_lines($row['emails'], 'EMAIL',
    fn($r) => escape($r['email']), fn($r) => @$r['label'], CARDDAV_GENERIC_TYPES, $internet, $groups);
  $body .= child_lines($row['phone_numbers'], 'TEL',
    fn($r) => escape($r['phone_number']), fn($r) => @$r['label'], CARDDAV_TEL_TYPES, [], $groups);
  $body .= address_lines($row['addresses'], $groups);
  $body .= child_lines($row['urls'], 'URL',
    fn($r) => escape($r['url']), fn($r) => @$r['label'], CARDDAV_GENERIC_TYPES, [], $groups);
  $body .= social_lines($row['socials'], 'org');

  if($row['timezone']) $body .= line('TZ', escape($row['timezone']), [['name' => 'VALUE', 'values' => ['TEXT']]]);
  $body .= categories_line($row['tags']);

  if(is_str($row['legal_name'])) $body .= line('X-EVERYTHING-LEGAL-NAME', escape($row['legal_name']));
  if(is_str($row['registration_number'])) $body .= line('X-EVERYTHING-REGISTRATION', escape($row['registration_number']));
  if(is_str($row['vat_number'])) $body .= line('X-EVERYTHING-VAT', escape($row['vat_number']));

  if(is_str($row['note'])) $body .= line('NOTE', escape($row['note']));
  $body .= line('X-EVERYTHING-SCHEMA', '1');
  $body .= retained_lines($row['properties']);
  return $body . "END:VCARD\r\n";
}

function serialize_tag($resource, $row) {
  $body = header_lines($resource, $row['label'], [$row['label'], "", "", "", ""]);
  $body .= line('KIND', 'group');
  $body .= line('X-ADDRESSBOOKSERVER-KIND', 'group');

  $uids = [];
  foreach($row['members'] as $contact_id) {
    $member = \store\get_carddav_resource('contact', $contact_id);
    if($member) $uids[] = member_ref($member['uid']);
  }
  sort($uids);
  foreach($uids as $uid) $body .= line('MEMBER', $uid);
  foreach($uids as $uid) $body .= line('X-ADDRESSBOOKSERVER-MEMBER', $uid);

  $body .= line('X-EVERYTHING-SCHEMA', '1');
  $body .= retained_lines($row['properties']);
  return $body . "END:VCARD\r\n";
}

// Fingerprints: a deterministic hash over everything client-visible in a
// card, minus the REV/revision bookkeeping. Reconciliation compares these
// to catch application-side edits without mutation hooks in controllers.

function fingerprint($row) {
  return hash('sha256', json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

// Reconciliation: mirror every contact, organisation and non-empty contact
// tag into carddav_resources, bump revisions for changed fingerprints and
// drop resources whose entity is gone.

function reconcile() {
  \store\transaction(function() {
    $existing = [];
    foreach(\store\list_carddav_resources() as $resource)
      $existing[$resource['entity_type'] . ':' . $resource['entity_id']] = $resource;

    $seen = [];
    $changes = [];

    $visit = function($type, $id, $desired, $fingerprint, $uid = null) use (&$existing, &$seen, &$changes) {
      $key = "$type:$id";
      $seen[$key] = true;
      $resource = @$existing[$key];

      if(!$resource) {
        $uid ??= \generate_uuid();
        $href = $uid . ".vcf";
        \store\update_carddav_resource($type, $id, $href, $desired, uid: $uid, fingerprint: $fingerprint);
        $changes[] = ['collection' => $desired, 'href' => $href, 'operation' => 'upsert'];
        return;
      }

      if($resource['collection'] != $desired) {
        if($resource['collection']) $changes[] = [
          'collection' => $resource['collection'], 'href' => $resource['href'], 'operation' => 'delete',
        ];
        \store\update_carddav_resource($type, $id, $resource['href'], $desired);
        \store\touch_carddav_resource($type, $id, $fingerprint);
        $changes[] = ['collection' => $desired, 'href' => $resource['href'], 'operation' => 'upsert'];
        return;
      }

      if($resource['fingerprint'] != $fingerprint) {
        \store\touch_carddav_resource($type, $id, $fingerprint);
        $changes[] = ['collection' => $desired, 'href' => $resource['href'], 'operation' => 'upsert'];
      }
    };

    foreach(book()['contacts'] as $id => $row)
      $visit('contact', $id, 'contacts', fingerprint($row));
    foreach(book()['organisations'] as $id => $row)
      $visit('organisation', $id, 'organisations', fingerprint($row));
    foreach(book()['tags'] as $id => $row)
      if($row['members'])
        $visit('tag', $id, 'contacts', fingerprint($row), uid: "tag-$id");

    foreach($existing as $key => $resource) {
      if(isset($seen[$key])) continue;
      if($resource['collection']) $changes[] = [
        'collection' => $resource['collection'],
        'href' => $resource['href'],
        'operation' => 'delete',
      ];
      \store\delete_carddav_resource($resource['entity_type'], $resource['entity_id']);
    }

    if($changes) \store\put_carddav_changes($changes);
  });
}

// Records a fresh fingerprint and change entry after a CardDAV write, so
// the next reconciliation does not double-report it.
function commit($type, $id) {
  forget();
  $resource = \store\get_carddav_resource($type, $id);
  if(!$resource || !$resource['collection']) return;
  \store\touch_carddav_resource($type, $id, fingerprint(entity($resource)));
  \store\put_carddav_changes([[
    'collection' => $resource['collection'],
    'href' => $resource['href'],
    'operation' => 'upsert',
  ]]);
}

// Parsing. Accepts vCard 2.1, 3.0 and 4.0; whatever the native model and
// the recognised compatibility aliases do not consume is retained verbatim
// (group, name, ordered parameters, raw value, position, duplicates).

function unescape($value) {
  return \icalendar\unescape_text((string)$value);
}

function parse($body) {
  if(substr_count(strtoupper($body), 'BEGIN:VCARD') != 1)
    throw new \InvalidArgumentException("Expected exactly one VCARD component.");

  try { $card = Reader::read($body); }
  catch(\Throwable $e) { throw new \InvalidArgumentException("Invalid vCard object: {$e->getMessage()}"); }

  if(!$card instanceof VCard)
    throw new \InvalidArgumentException("Expected VCARD, received {$card->name}.");
  $version = (string)$card->VERSION;
  if(!in_array($version, ['2.1', '3.0', '4.0']))
    throw new \InvalidArgumentException("Unsupported vCard version '$version'.");
  if(!is_str($card->UID))
    throw new \InvalidArgumentException("VCARD has no UID.");

  return $card;
}

// Client UIDs are stored verbatim so their cards round-trip untouched;
// references tolerate an urn:uuid: prefix in either direction.
function card_uid(VCard $card) {
  return trim((string)$card->UID);
}

function member_ref($uid) {
  return str_starts_with(strtolower($uid), 'urn:') ? $uid : 'urn:uuid:' . $uid;
}

function resource_by_uid($uid) {
  $uid = trim($uid);
  return \store\get_carddav_resource_by_uid($uid)
    ?: \store\get_carddav_resource_by_uid(preg_replace('/^urn:uuid:/i', '', $uid))
    ?: \store\get_carddav_resource_by_uid('urn:uuid:' . $uid);
}

function card_kind(VCard $card) {
  $kind = strtolower(trim((string)$card->KIND));
  $server_kind = strtolower(trim((string)$card->{'X-ADDRESSBOOKSERVER-KIND'}));
  if($kind == 'group' || $server_kind == 'group') return 'tag';
  if($kind == 'org' || $kind == 'organization'
    || strtoupper(trim((string)$card->{'X-ABShowAs'})) == 'COMPANY') return 'organisation';
  return 'contact';
}

// The consumption bag: parsing walks the card, takes what the model
// understands and leaves the rest for retention.

function bag(VCard $card) {
  $properties = [];
  foreach($card->children() as $child)
    if($child instanceof Property) $properties[] = $child;
  return ['properties' => $properties, 'consumed' => new \SplObjectStorage()];
}

function take(&$bag, $name, $group = null) {
  return @take_all($bag, $name, $group)[0];
}

function take_all(&$bag, $name, $group = null) {
  $taken = [];
  foreach($bag['properties'] as $property) {
    if($bag['consumed']->contains($property)) continue;
    if(strtoupper($property->name) != strtoupper($name)) continue;
    if($group !== null && strtoupper((string)$property->group) != strtoupper($group)) continue;
    $bag['consumed']->attach($property);
    $taken[] = $property;
  }
  return $taken;
}

function leftovers(&$bag) {
  $rows = [];
  foreach($bag['properties'] as $property) {
    if($bag['consumed']->contains($property)) continue;
    $rows[] = retained_row($property);
  }
  return $rows;
}

function retained_row(Property $property) {
  return [
    'group' => $property->group ? strtoupper($property->group) : null,
    'name' => strtoupper($property->name),
    'parameters' => \caldav\parameters($property),
    'value' => $property->getRawMimeDirValue(),
  ];
}

// Grouped X-ABLabel lookup: resolves the label for a consumed property and
// consumes the label line along with it.
function group_label(&$bag, Property $property) {
  if(!$property->group) return null;
  $label = take($bag, 'X-ABLabel', group: $property->group);
  if(!$label) return null;
  $text = trim($label->getValue());
  return @CARDDAV_APPLE_LABELS[$text] ?: $text;
}

function type_label(Property $property, $reverse) {
  $types = isset($property['TYPE']) ? $property['TYPE']->getParts() : [];
  foreach($types as $type)
    if(isset($reverse[strtoupper($type)])) return $reverse[strtoupper($type)];
  return null;
}

function child_label(&$bag, Property $property, $types) {
  $label = group_label($bag, $property);
  if($label !== null) return $label;
  return type_label($property, array_flip(array_map('strtoupper', $types)));
}

function parse_date_parts(Property $property) {
  $raw = trim($property->getRawMimeDirValue());
  $raw = preg_replace('/[T ].*$/', '', $raw);
  $omit = isset($property['X-APPLE-OMIT-YEAR']);

  if(preg_match('/^--(\d{2})-?(\d{2})$/', $raw, $match))
    return [(int)$match[2], (int)$match[1], null];
  if(preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/', $raw, $match)) {
    $year = (int)$match[1];
    if($omit || $year == 1604) $year = null;
    return [(int)$match[3], (int)$match[2], $year];
  }
  return null;
}

function parse_children(&$bag, $name, $types, $value_of) {
  $rows = [];
  foreach(take_all($bag, $name) as $property) {
    $label = child_label($bag, $property, $types);
    $value = $value_of($property);
    if(!is_str($value)) continue;
    $rows[] = ['label' => $label, 'value' => trim($value)];
  }
  return $rows;
}

function dedupe($rows, $key_of) {
  $result = [];
  foreach($rows as $row) {
    $key = $key_of($row);
    if(isset($result[$key])) continue;
    $result[$key] = $row;
  }
  return array_values($result);
}

function parse_addresses(&$bag, &$retained) {
  $rows = [];
  foreach(take_all($bag, 'ADR') as $property) {
    $label = child_label($bag, $property, CARDDAV_GENERIC_TYPES);
    $apple_country = $property->group ? take($bag, 'X-ABADR', group: $property->group) : null;
    $identity = $property->group ? take($bag, 'X-EVERYTHING-ADDRESS', group: $property->group) : null;

    $parts = array_map(
      fn($part) => is_array($part) ? implode(", ", $part) : $part,
      array_pad($property->getParts(), 7, ""));
    [$pobox, $extended, $street, $city, $province, $code, $country] = $parts;
    $street = str_implode(", ", [$pobox, $extended, $street]);

    $country_in = \cast_str($country);
    $iso = country_code($country_in ?? ($apple_country ? trim($apple_country->getValue()) : ""));
    if($country_in !== null && $iso === null) {
      // An unrecognised country cannot be stored without inventing a code;
      // the whole ADR stays a retained property instead.
      \logger\warn("Retained CardDAV address with unrecognised country '$country_in'.");
      $retained[] = retained_row($property);
      if($apple_country) $retained[] = retained_row($apple_country);
      continue;
    }
    if(trim($street) == "") {
      $retained[] = retained_row($property);
      if($apple_country) $retained[] = retained_row($apple_country);
      continue;
    }

    $row = [
      'label' => $label,
      'street_address' => trim($street),
      'postal_code' => \cast_str($code),
      'city' => \cast_str($city),
      'province' => \cast_str($province),
      'country' => $iso,
    ];

    if($identity && ctype_digit(trim($identity->getValue()))) {
      $address = \store\get_address((int)trim($identity->getValue()));
      if($address) $row['id'] = $address['id'];
    }

    $rows[] = $row;
  }
  return dedupe($rows, fn($row) => json_encode([
    $row['street_address'], @$row['postal_code'], @$row['city'], @$row['country'],
  ]));
}

function parse_socials(&$bag, &$retained) {
  $aliases = ['mastodon' => 'activitypub', 'bluesky' => 'bsky', 'x' => 'twitter'];
  $rows = [];

  foreach([...take_all($bag, 'X-SOCIALPROFILE'), ...take_all($bag, 'SOCIALPROFILE')] as $property) {
    $type = strtolower(trim(isset($property['TYPE']) ? (string)$property['TYPE'] : ""));
    if($type == "" && isset($property['X-SERVICE-TYPE'])) $type = strtolower(trim((string)$property['X-SERVICE-TYPE']));
    $type = @$aliases[$type] ?: $type;
    if(!in_array($type, ENUM_SOCIAL_TYPE)) {
      $retained[] = retained_row($property);
      continue;
    }

    $handle = isset($property['X-USER']) ? trim((string)$property['X-USER']) : "";
    if($handle == "") {
      $value = preg_replace('/^x-apple:/i', '', trim($property->getValue()));
      $path = parse_url($value, PHP_URL_PATH) ?: $value;
      $handle = ltrim(rawurldecode(basename($path)), "@");
    }
    if($handle == "") { $retained[] = retained_row($property); continue; }
    $rows[] = ['type' => $type, 'handle' => $handle];
  }

  foreach(take_all($bag, 'IMPP') as $property) {
    $value = trim($property->getValue());
    if(preg_match('/^matrix:(.+)$/i', $value, $match))
      $rows[] = ['type' => 'matrix', 'handle' => $match[1]];
    else $retained[] = retained_row($property);
  }

  return dedupe($rows, fn($row) => $row['type'] . '|' . mb_strtolower($row['handle']));
}

// Membership through CATEGORIES may only target existing, uniquely
// labelled tags; unknown or ambiguous labels never create or drop
// anything they do not clearly name.
function parse_categories(&$bag, $current_tags) {
  $categories = take_all($bag, 'CATEGORIES');
  if(!$categories) return null;

  $labels = [];
  foreach($categories as $property)
    foreach((array)$property->getParts() as $part)
      if(is_str($part)) $labels[str_normalize(trim($part))] = true;

  $matches = [];
  foreach(\store\list_tag_rows() as $tag) {
    $key = str_normalize($tag['label']);
    if(!isset($labels[$key])) continue;
    $matches[$key][] = $tag['id'];
  }

  $tag_ids = [];
  foreach($matches as $key => $ids)
    if(count($ids) == 1) $tag_ids[] = $ids[0];

  // Ambiguous labels keep whatever membership already exists.
  foreach($current_tags as $tag) {
    $key = str_normalize($tag['label']);
    if(isset($labels[$key]) && count(@$matches[$key] ?: []) != 1) $tag_ids[] = $tag['id'];
  }

  return array_values(array_unique($tag_ids));
}

function parse_picture(&$bag) {
  $picture = take($bag, 'PHOTO');
  take_all($bag, 'PHOTO');
  if(!$picture) return null;

  if($picture instanceof \Sabre\VObject\Property\Binary) {
    return \contacts\normalize_binary_picture($picture->getValue());
  }

  $value = trim($picture->getValue());

  if(preg_match('#^data:image/(?:jpeg|png|webp|gif);base64,(.+)$#is', $value, $match)) {
    return \contacts\normalize_base64_picture($match[1]);
  }

  if(preg_match('#^https?://#i', $value)) {
    return \contacts\normalize_remote_picture($value);
  }

  throw new \InvalidArgumentException("VCARD PHOTO has an unsupported value.");
}

function parse_contact(VCard $card, $current) {
  $bag = bag($card);
  $retained = [];
  foreach(['VERSION', 'PRODID', 'UID', 'REV', 'KIND', 'X-EVERYTHING-SCHEMA', 'X-EVERYTHING-NAME'] as $ignored)
    take_all($bag, $ignored);

  // Names. N carries the display form: the first name and the surname
  // that name_order puts first. An edited surname lands on the displayed
  // component; the structured split of the other component survives.
  $n = take($bag, 'N');
  take_all($bag, 'N');
  $parts = array_map(
    fn($part) => is_array($part) ? implode(" ", $part) : $part,
    array_pad($n ? $n->getParts() : [], 5, ""));
  [$surname_in, $first_in, $middle_in, $prefix_in, $suffix_in] = array_map('trim', $parts);

  $fn_property = take($bag, 'FN');
  take_all($bag, 'FN');
  $fn = $fn_property ? trim($fn_property->getValue()) : "";

  // CardDAV clients only see the override in N and therefore cannot edit the
  // hidden structured name. Name edits update the override until it is cleared
  // in the database and the structured name becomes visible again.
  $display_override = $current && is_str($current['display_name']);
  $fields = [
    'first_name' => $display_override ? $current['first_name'] : $first_in,
    'middle_name' => $display_override ? $current['middle_name'] : \cast_str($middle_in),
    'family_infix' => $current ? $current['family_infix'] : null,
    'family_name' => $current ? $current['family_name'] : null,
    'legal_infix' => $current ? $current['legal_infix'] : null,
    'legal_name' => $current ? $current['legal_name'] : null,
    'name_order' => $current ? $current['name_order'] : 'family_legal',
  ];
  if(!$display_override && (!$current || $surname_in != \contacts\contact_display_surname($current))) {
    if($current && $current['name_order'] == 'legal_family' && is_str($current['legal_name']))
      [$fields['legal_infix'], $fields['legal_name']] = split_surname($surname_in, @$current['legal_infix']);
    else
      [$fields['family_infix'], $fields['family_name']] = split_surname($surname_in, @$current['family_infix']);
  }
  if($fields['first_name'] == "") $fields['first_name'] = \cast_str($surname_in) ?? $fn;
  if(!is_str($fields['first_name']))
    throw new \InvalidArgumentException("VCARD contains no usable name.");
  if($fields['first_name'] == $fields['family_name'] && $first_in == "") $fields['family_name'] = null;

  if($prefix_in != "") $retained[] = ['group' => null, 'name' => 'X-EVERYTHING-N-PREFIX', 'parameters' => [], 'value' => escape($prefix_in)];
  if($suffix_in != "") $retained[] = ['group' => null, 'name' => 'X-EVERYTHING-N-SUFFIX', 'parameters' => [], 'value' => escape($suffix_in)];

  $nickname = take($bag, 'NICKNAME');
  take_all($bag, 'NICKNAME');
  $fields['nickname'] = $nickname ? \cast_str($nickname->getValue()) : null;

  $computed = \contacts\contact_display_name([...$fields, 'display_name' => null, 'nickname' => null]);
  $fields['display_name'] = ($fn == "" || $fn == $computed) ? null : $fn;

  $pronouns = take($bag, 'PRONOUNS') ?: take($bag, 'X-PRONOUNS');
  take_all($bag, 'PRONOUNS');
  take_all($bag, 'X-PRONOUNS');
  $fields['pronouns'] = $pronouns ? \cast_str($pronouns->getValue()) : null;

  $fields = [...$fields, 'birth_day' => null, 'birth_month' => null, 'birth_year' => null];
  $bday = take($bag, 'BDAY');
  take_all($bag, 'BDAY');
  if($bday && ($date = parse_date_parts($bday)))
    [$fields['birth_day'], $fields['birth_month'], $fields['birth_year']] = $date;
  elseif($bday) $retained[] = retained_row($bday);

  $fields = [...$fields, 'anniversary_day' => null, 'anniversary_month' => null, 'anniversary_year' => null];
  $anniversary = take($bag, 'ANNIVERSARY') ?: take($bag, 'X-ANNIVERSARY');
  take_all($bag, 'ANNIVERSARY');
  take_all($bag, 'X-ANNIVERSARY');
  foreach(take_all($bag, 'X-ABDATE') as $property) {
    $label = group_label($bag, $property);
    if(!$anniversary && $label == 'anniversary') { $anniversary = $property; continue; }
    $row = retained_row($property);
    $retained[] = $row;
    if($label !== null) $retained[] = [
      'group' => $row['group'], 'name' => 'X-ABLABEL', 'parameters' => [],
      'value' => escape(@array_flip(CARDDAV_APPLE_LABELS)[$label] ?: $label),
    ];
  }
  if($anniversary && ($date = parse_date_parts($anniversary)))
    [$fields['anniversary_day'], $fields['anniversary_month'], $fields['anniversary_year']] = $date;
  elseif($anniversary) $retained[] = retained_row($anniversary);

  $timezone = take($bag, 'TZ');
  take_all($bag, 'TZ');
  $fields['timezone'] = null;
  if($timezone) {
    $value = trim($timezone->getValue());
    if(in_array($value, ENUM_TIMEZONE)) $fields['timezone'] = $value;
    else $retained[] = retained_row($timezone);
  }

  $note = take($bag, 'NOTE');
  take_all($bag, 'NOTE');
  $fields['note'] = $note ? \cast_str($note->getValue()) : null;

  $emails = dedupe(
    array_map(fn($row) => ['label' => $row['label'], 'email' => $row['value']],
      parse_children($bag, 'EMAIL', CARDDAV_GENERIC_TYPES, fn($p) => $p->getValue())),
    fn($row) => mb_strtolower($row['email']));
  $phones = dedupe(
    array_map(fn($row) => ['label' => $row['label'], 'phone_number' => normalize_phone_number($row['value'])],
      parse_children($bag, 'TEL', CARDDAV_TEL_TYPES, fn($p) => $p->getValue())),
    fn($row) => $row['phone_number']);
  $urls = dedupe(
    array_map(fn($row) => ['label' => $row['label'], 'url' => $row['value']],
      parse_children($bag, 'URL', CARDDAV_GENERIC_TYPES, fn($p) => $p->getValue())),
    fn($row) => $row['url']);
  $addresses = parse_addresses($bag, $retained);
  $socials = parse_socials($bag, $retained);

  $tags = parse_categories($bag, $current ? $current['tags'] : []);
  $roles = parse_roles($bag, $current, $retained);
  $picture = parse_picture($bag);

  return [
    'fields' => $fields,
    'emails' => $emails,
    'phones' => $phones,
    'urls' => $urls,
    'addresses' => $addresses,
    'socials' => $socials,
    'tags' => $tags,
    'roles' => $roles,
    'picture' => $picture,
    'properties' => [...$retained, ...leftovers($bag)],
  ];
}

// An edited surname keeps its structured split when the stored infix
// still prefixes the incoming text; otherwise the whole string becomes
// the bare name.
function split_surname($value, $infix) {
  $value = trim((string)$value);
  if($value == "") return [null, null];

  $infix = trim((string)$infix);
  if($infix != "" && str_starts_with($value, $infix . " "))
    return [$infix, \cast_str(substr($value, strlen($infix) + 1))];

  return [null, \cast_str($value)];
}

// Roles: X-EVERYTHING-ROLE properties are authoritative when present.
// Without them the current roles survive, and only the native ORG/TITLE
// pair is reconciled: relinking happens solely on an exact, unique
// normalized organisation name match. An ORG that matches nothing (or
// several organisations) is dropped with a warning: contact_roles cannot
// hold a free-form organisation, and a half-alive retained copy would
// drift out of sync with the model.
function parse_roles(&$bag, $current, &$retained) {
  $current_roles = $current ? array_map(fn($role) => [
    'org_id' => $role['org_id'],
    'role' => $role['role'],
    'main' => \cast_bool($role['main']) ? 1 : 0,
    'organisation_name' => $role['organisation_name'],
  ], $current['roles']) : [];

  $role_props = take_all($bag, 'X-EVERYTHING-ROLE');
  $roles = $current_roles;
  if($role_props) {
    $roles = [];
    foreach($role_props as $property) {
      [$uid, $primary, $role] = array_pad($property->getParts(), 3, "");
      $resource = $uid ? resource_by_uid(trim($uid)) : null;
      if(!$resource || $resource['entity_type'] != 'organisation') {
        \logger\warn("Retained CardDAV role with unknown organisation reference.");
        $retained[] = retained_row($property);
        continue;
      }
      $organisation = \store\get_organisation($resource['entity_id']);
      if(!$organisation) { $retained[] = retained_row($property); continue; }
      $roles[] = [
        'org_id' => $resource['entity_id'],
        'role' => cast_str($role),
        'main' => cast_bool($primary) ? 1 : 0,
        'organisation_name' => $organisation['display_name'],
      ];
    }
  }

  $org_property = take($bag, 'ORG');
  take_all($bag, 'ORG');
  $title_property = take($bag, 'TITLE') ?: take($bag, 'ROLE');
  take_all($bag, 'TITLE');
  take_all($bag, 'ROLE');

  $org_in = null;
  if($org_property) {
    $parts = $org_property->getParts();
    $org_in = \cast_str(is_array(@$parts[0]) ? implode(" ", $parts[0]) : @$parts[0]);
  }
  $title_in = $title_property ? \cast_str($title_property->getValue()) : null;

  $primary_index = null;
  foreach($roles as $index => $role)
    if($role['main']) { $primary_index = $index; break; }
  if($primary_index === null && $roles) $primary_index = 0;
  $primary_name = $primary_index !== null ? $roles[$primary_index]['organisation_name'] : null;

  if($org_in === null) {
    if($primary_index !== null && !$role_props) array_splice($roles, $primary_index, 1);
  }
  elseif($primary_name === null || str_normalize($org_in) != str_normalize($primary_name)) {
    $matches = \store\list_organisations_by_normalized_name($org_in);
    if(count($matches) == 1) {
      $linked = [
        'org_id' => $matches[0]['id'],
        'role' => $title_in,
        'main' => 1,
        'organisation_name' => $matches[0]['display_name'],
      ];
      if($primary_index !== null) $roles[$primary_index] = $linked;
      else {
        foreach($roles as &$role) $role['main'] = 0;
        unset($role);
        $roles[] = $linked;
      }
    }
    else {
      \logger\warn("Dropped CardDAV ORG '$org_in': " . count($matches) . " organisations match.");
    }
  }
  elseif($primary_index !== null) {
    $roles[$primary_index]['role'] = $title_in;
  }

  return array_map(fn($role) => [
    'org_id' => $role['org_id'],
    'role' => $role['role'],
    'main' => $role['main'],
  ], $roles);
}

function parse_organisation(VCard $card, $current) {
  $bag = bag($card);
  $retained = [];
  foreach(['VERSION', 'PRODID', 'UID', 'REV', 'KIND', 'N', 'X-ABShowAs', 'X-EVERYTHING-SCHEMA'] as $ignored)
    take_all($bag, $ignored);

  $fn_property = take($bag, 'FN');
  take_all($bag, 'FN');
  $org_property = take($bag, 'ORG');
  take_all($bag, 'ORG');
  $org_name = null;
  if($org_property) {
    $parts = $org_property->getParts();
    $org_name = \cast_str(is_array(@$parts[0]) ? implode(" ", $parts[0]) : @$parts[0]);
  }

  $display_name = ($fn_property ? \cast_str($fn_property->getValue()) : null) ?? $org_name;
  if($display_name === null)
    throw new \InvalidArgumentException("Organisation card has no FN or ORG name.");

  $fields = ['display_name' => $display_name];
  foreach([
    'legal_name' => 'X-EVERYTHING-LEGAL-NAME',
    'registration_number' => 'X-EVERYTHING-REGISTRATION',
    'vat_number' => 'X-EVERYTHING-VAT',
  ] as $field => $name) {
    $property = take($bag, $name);
    take_all($bag, $name);
    $fields[$field] = $property
      ? \cast_str($property->getValue())
      : ($current ? $current[$field] : null);
  }

  $timezone = take($bag, 'TZ');
  take_all($bag, 'TZ');
  $fields['timezone'] = null;
  if($timezone) {
    $value = trim($timezone->getValue());
    if(in_array($value, ENUM_TIMEZONE)) $fields['timezone'] = $value;
    else $retained[] = retained_row($timezone);
  }

  $note = take($bag, 'NOTE');
  take_all($bag, 'NOTE');
  $fields['note'] = $note ? \cast_str($note->getValue()) : null;

  $emails = dedupe(
    array_map(fn($row) => ['label' => $row['label'], 'email' => $row['value']],
      parse_children($bag, 'EMAIL', CARDDAV_GENERIC_TYPES, fn($p) => $p->getValue())),
    fn($row) => mb_strtolower($row['email']));
  $phones = dedupe(
    array_map(fn($row) => ['label' => $row['label'], 'phone_number' => normalize_phone_number($row['value'])],
      parse_children($bag, 'TEL', CARDDAV_TEL_TYPES, fn($p) => $p->getValue())),
    fn($row) => $row['phone_number']);
  $urls = dedupe(
    array_map(fn($row) => ['label' => $row['label'], 'url' => $row['value']],
      parse_children($bag, 'URL', CARDDAV_GENERIC_TYPES, fn($p) => $p->getValue())),
    fn($row) => $row['url']);
  $addresses = parse_addresses($bag, $retained);
  $socials = parse_socials($bag, $retained);
  $tags = parse_categories($bag, $current ? $current['tags'] : []);
  $picture = parse_picture($bag);

  return [
    'fields' => $fields,
    'emails' => $emails,
    'phones' => $phones,
    'urls' => $urls,
    'addresses' => $addresses,
    'socials' => $socials,
    'tags' => $tags,
    'picture' => $picture,
    'properties' => [...$retained, ...leftovers($bag)],
  ];
}

function parse_group(VCard $card, $tag) {
  $bag = bag($card);
  foreach(['VERSION', 'PRODID', 'UID', 'REV', 'N', 'KIND', 'X-ADDRESSBOOKSERVER-KIND', 'X-EVERYTHING-SCHEMA'] as $ignored)
    take_all($bag, $ignored);

  $fn_property = take($bag, 'FN');
  take_all($bag, 'FN');
  $fn = $fn_property ? trim($fn_property->getValue()) : "";
  if($fn != trim($tag['label']))
    throw new \InvalidArgumentException("Tag groups cannot be renamed through CardDAV.");

  $member_ids = [];
  $unknown = [];
  foreach([...take_all($bag, 'X-ADDRESSBOOKSERVER-MEMBER'), ...take_all($bag, 'MEMBER')] as $property) {
    $uid = trim($property->getValue());
    $resource = resource_by_uid($uid);
    if(!$resource || $resource['entity_type'] != 'contact') $unknown[] = $uid;
    else $member_ids[] = $resource['entity_id'];
  }

  return [
    'member_ids' => array_values(array_unique($member_ids)),
    'unknown' => array_values(array_unique($unknown)),
    'properties' => leftovers($bag),
  ];
}
