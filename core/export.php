<?php
// Git export: mirrors the complete database state into a dedicated Git
// repository, one commit per mutating request. The exporter owns its state
// (lock, pending marker, manifest) under the repository's .git directory,
// so the worktree only ever contains exported data.

// Lovingly written by Claude.

namespace export;

define('EXPORT_SCHEMA', 1);

$_EXPORT_LOCK = null;   // held flock handle
$_EXPORT_REPO = null;   // repository the lock belongs to
$_EXPORT_ACTIVE = false; // this request wrote a pending marker and must export

// Configuration

function enabled() {
  return cast_bool(DEVELOPER_GIT_ENABLED);
}

function repository() {
  return \config\canonical_value('developer.git-repository');
}

function active() {
  global $_EXPORT_ACTIVE;
  return $_EXPORT_ACTIVE;
}

// Repository plumbing

function git($repo, ...$args) {
  return git_c($repo, ...$args);
}

function must_git($repo, ...$args) {
  [$exit, $output] = git($repo, ...$args);
  if($exit) fail("Git failed in the export repository: $output");
  return $output;
}

function git_dir($repo) {
  static $cache = [];
  if(array_key_exists($repo, $cache)) return $cache[$repo];

  // A .git directory directly under the path makes it a worktree root by
  // construction. Only linked worktrees and submodules need to ask Git.
  if(is_dir("$repo/.git")) return $cache[$repo] = "$repo/.git";

  [$exit, $root] = git($repo, 'rev-parse', '--show-toplevel');
  if($exit || realpath($root) != realpath($repo)) return $cache[$repo] = null;

  [$exit, $output] = git($repo, 'rev-parse', '--absolute-git-dir');
  return $cache[$repo] = ($exit ? null : $output);
}

function validate($repo, $status = 503) {
  if(!is_str($repo)) fail("No Git export repository is configured.", status: $status);
  if(!is_dir($repo)) fail("Git export repository '$repo' does not exist.", status: $status);
  if(!git_dir($repo)) fail("Git export repository '$repo' is not an initialized Git worktree.", status: $status);
  return $repo;
}

function worktree_clean($repo) {
  return must_git($repo, 'status', '--porcelain') == '';
}

function state_dir($repo) {
  $dir = git_dir($repo) . "/everything";
  if(!is_dir($dir)) mkdir($dir);
  return $dir;
}

function atomic_write($path, $content) {
  $tmp = "$path.tmp";
  if(file_put_contents($tmp, $content) === false) fail("Could not write '$tmp'.");
  if(!rename($tmp, $path)) fail("Could not move '$tmp' into place.");
}

// Locking. The lock file lives under .git and is held for the whole
// request, serializing every exported mutation on this repository.

function lock($repo) {
  global $_EXPORT_LOCK, $_EXPORT_REPO;
  if($_EXPORT_LOCK) return;

  $handle = @fopen(state_dir($repo) . "/lock", "c");
  if(!$handle || !flock($handle, LOCK_EX))
    fail("Could not lock the export repository.", status: 503);

  $_EXPORT_LOCK = $handle;
  $_EXPORT_REPO = $repo;
}

function unlock() {
  global $_EXPORT_LOCK, $_EXPORT_ACTIVE;
  if(!$_EXPORT_LOCK) return;

  flock($_EXPORT_LOCK, LOCK_UN);
  fclose($_EXPORT_LOCK);
  $_EXPORT_LOCK = null;
  $_EXPORT_ACTIVE = false;
}

// Pending marker, initialization marker and generated-path manifest.

function read_pending($repo) {
  $body = @file_get_contents(state_dir($repo) . "/pending.json");
  return $body ? json_decode($body, true) : null;
}

function write_pending($repo, $data) {
  atomic_write(state_dir($repo) . "/pending.json", json_encode($data, JSON_UNESCAPED_SLASHES) . "\n");
}

function clear_pending($repo) {
  @unlink(state_dir($repo) . "/pending.json");
}

function initialized($repo) {
  return is_file(state_dir($repo) . "/initialized");
}

function mark_initialized($repo) {
  atomic_write(state_dir($repo) . "/initialized", gmdate('c') . "\n");
}

function read_manifest($repo) {
  $body = @file_get_contents(state_dir($repo) . "/manifest.json");
  $manifest = $body ? (json_decode($body, true) ?: []) : [];
  // Manifests written before content hashing were plain path lists.
  return array_is_list($manifest) ? array_fill_keys($manifest, null) : $manifest;
}

function write_manifest($repo, $hashes) {
  atomic_write(state_dir($repo) . "/manifest.json",
    json_encode($hashes, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
}

// Request lifecycle. guard() runs for every mutating request while export
// is enabled, before the database transaction opens: it locks the
// repository, retries or rejects a pending export and records this
// request's marker. before_commit() and after_commit() are called by
// anyhow around the request transaction commit; abandon() runs when the
// request fails before its commit.

function guard($method, $path) {
  if(!enabled()) return;

  $repo = repository();
  validate($repo);
  lock($repo);

  if(!initialized($repo))
    fail("Git export is enabled but the repository has no baseline; run 'php export.php initialize'.", status: 503);

  recover($repo);
  begin($repo, $method, $path);
}

function begin($repo, $method, $path) {
  global $_EXPORT_ACTIVE;

  write_pending($repo, [
    'method' => $method,
    'path' => $path,
    'started_at' => gmdate('c'),
    'request_id' => generate_uuid(),
  ]);
  $_EXPORT_ACTIVE = true;
}

function recover($repo) {
  $pending = read_pending($repo);

  if(!$pending) {
    if(!worktree_clean($repo))
      fail("The export repository has uncommitted changes but no pending export; refusing to mutate.", status: 503);
    return;
  }

  try {
    commit_state($repo, "{$pending['method']} {$pending['path']}");
  }
  catch(\Throwable $error) {
    fail("Recovering the pending export failed: {$error->getMessage()}", status: 503);
  }
  clear_pending($repo);
}

function before_commit() {
  global $_EXPORT_ACTIVE;
  if(!$_EXPORT_ACTIVE) return;
  reconcile_identities();
}

function after_commit() {
  global $_EXPORT_ACTIVE, $_EXPORT_REPO;
  if(!$_EXPORT_ACTIVE) return;

  // Run the export asynchronously, send the
  // response to the client already.
  flush_response();

  $pending = read_pending($_EXPORT_REPO);
  $message = $pending ? "{$pending['method']} {$pending['path']}" : "Export";
  export_and_commit($_EXPORT_REPO, $message);
  clear_pending($_EXPORT_REPO);
  unlock();
}

function abandon() {
  global $_EXPORT_ACTIVE, $_EXPORT_REPO;
  if($_EXPORT_ACTIVE) clear_pending($_EXPORT_REPO);
  unlock();
}

// Enable lifecycle, called by the Developer Git action while export is
// still disabled: locks the submitted repository, creates the baseline
// commit if needed and registers the enabling request for export.

function activate($repo, $method, $path) {
  validate($repo, status: 409);
  lock($repo);

  if(initialized($repo)) recover($repo);
  else initialize_baseline($repo);

  begin($repo, $method, $path);
}

// Creates the repository for CLI initialization: the directory when it is
// missing (its parent must exist and be writable), and the Git worktree
// when the directory is not one yet.
function create_repository($repo) {
  if(!is_str($repo)) fail("No Git export repository is configured.");

  if(!is_dir($repo)) {
    $parent = dirname($repo);
    if(!is_dir($parent)) fail("Parent directory '$parent' does not exist.");
    if(!is_writable($parent)) fail("Parent directory '$parent' is not writable.");
    if(!mkdir($repo)) fail("Could not create '$repo'.");
  }

  [$exit, $root] = git($repo, 'rev-parse', '--show-toplevel');
  if($exit || realpath($root) != realpath($repo))
    must_git($repo, 'init', '--quiet');
}

function initialize_baseline($repo) {
  if(!worktree_clean($repo))
    fail("The export repository has uncommitted changes; commit or clean it before initializing.", status: 409);

  commit_state($repo, "Initialize export baseline");
  mark_initialized($repo);
}

// Exporting and committing

// Exports the complete database state and commits it under $message when
// anything changed. Reconciles DAV identities first; outside a request
// transaction they commit on their own.
function commit_state($repo, $message) {
  reconcile_identities();
  return export_and_commit($repo, $message, everything: true);
}

// A request starts against a clean worktree, so only the paths this export
// changed can differ from HEAD and normal requests stage just those (or
// skip Git entirely when nothing changed). Recovery and initialization
// cannot trust the worktree and stage every exporter-owned path.
function export_and_commit($repo, $message, $everything = false) {
  [$changed, $all] = write_tree($repo, tree());
  $paths = $everything ? $all : $changed;
  if(!$paths) return false;

  stage($repo, $paths);
  if(!staged_diff($repo)) return false;

  // Exported commits are machine-generated on every mutating request:
  // interactive commit hooks (--no-verify) and GPG signing (which costs
  // ~200ms per commit and would sign as the user, not the app) must not
  // slow down or wedge the application.
  must_git($repo, '-c', 'user.name=Everything', '-c', 'user.email=export@everything',
    '-c', 'commit.gpgsign=false',
    'commit', '--quiet', '--no-verify', '-m', $message);
  return true;
}

// Writes changed files through temporary files and atomic renames, and
// deletes previously generated paths absent from the tree. The manifest
// keeps a content hash per path, so unchanged files are skipped without
// reading them back. Returns the changed paths and the full set of
// exporter-owned paths.
function write_tree($repo, $tree) {
  ksort($tree);
  $manifest = read_manifest($repo);
  $hashes = [];
  $changed = [];

  foreach($tree as $path => $content) {
    $hashes[$path] = $hash = sha1($content);
    $target = "$repo/$path";
    if(@$manifest[$path] === $hash && is_file($target)) continue;
    if(!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
    atomic_write($target, $content);
    $changed[] = $path;
  }

  foreach($manifest as $path => $hash) {
    if(isset($tree[$path])) continue;
    @unlink("$repo/$path");
    $dir = dirname("$repo/$path");
    while($dir != $repo && @rmdir($dir)) $dir = dirname($dir);
    $changed[] = $path;
  }

  write_manifest($repo, $hashes);
  return [$changed, array_values(array_unique([...array_keys($manifest), ...array_keys($tree)]))];
}

// Stages exactly the exporter-owned paths, additions and deletions alike,
// through a NUL-separated pathspec file under .git.
function stage($repo, $paths) {
  if(!$paths) return;
  $pathspec = state_dir($repo) . "/pathspec";
  atomic_write($pathspec, implode("\0", $paths));
  must_git($repo, 'add', '-A', "--pathspec-from-file=$pathspec", '--pathspec-file-nul');
}

function staged_diff($repo) {
  [$exit, $output] = git($repo, 'diff', '--cached', '--quiet');
  if($exit == 0) return false;
  if($exit == 1) return true;
  fail("Could not inspect staged export changes: $output");
}

// Verification: builds the export tree without leaving a trace and reports
// paths where the repository disagrees with the database.

function verify($repo) {
  validate($repo);
  lock($repo);

  $tree = dry_tree();
  $differences = [];

  foreach($tree as $path => $content) {
    $current = @file_get_contents("$repo/$path");
    if($current === false) $differences[] = "missing: $path";
    elseif($current !== $content) $differences[] = "differs: $path";
  }

  foreach(read_manifest($repo) as $path => $hash)
    if(!isset($tree[$path]) && is_file("$repo/$path")) $differences[] = "stale: $path";

  return $differences;
}

function dry_tree() {
  DBH->beginTransaction();
  try {
    reconcile_identities();
    return tree();
  }
  finally {
    DBH->rollBack();
    \carddav\forget();
    \caldav\forget();
    forget_book();
  }
}

// Every appointment, contact and organisation needs a stable DAV identity
// before serialization. The DAV reconciliations cover everything visible;
// entities hidden from DAV collections get a resource row without a
// collection so their UID and DTSTAMP stay put.

function reconcile_identities() {
  \caldav\reconcile();
  \carddav\forget();
  \carddav\reconcile();

  \store\transaction(function() {
    foreach(\store\list_appointment_rows() as $row) {
      if(\store\get_caldav_resource('appointment', $row['id'])) continue;
      \store\update_caldav_resource('appointment', $row['id'], $row['id'] . ".ics", null);
    }
  });
}

// The exported tree: a complete path => content map of the database state.

function tree() {
  \caldav\preload();
  forget_book();

  $caldav = [];
  foreach(\store\list_caldav_resources() as $resource)
    $caldav[$resource['entity_type'] . ':' . $resource['entity_id']] = $resource;
  $carddav = [];
  foreach(\store\list_carddav_resources() as $resource)
    $carddav[$resource['entity_type'] . ':' . $resource['entity_id']] = $resource;

  $tree = [];

  $tree['config.json'] = json(config_map());
  $tree['tags.json'] = json(tags_data());
  $tree['addresses.json'] = json(addresses_data());
  $tree['calendars.json'] = json(calendars_data());
  $tree['subscriptions.json'] = json(subscriptions_data());
  $tree['habits.json'] = json(habits_data());
  $tree['quotas.json'] = json(quotas_data());

  foreach(timings_by_month() as $month => $rows)
    $tree["timings/$month.json"] = json($rows);

  foreach(\store\list_appointment_rows() as $row) {
    $folder = $row['calendar_id']
      ? "calendar/" . filename($row['calendar_id'])
      : "subscriptions/" . filename($row['subscription_id']);
    $resource = @$caldav['appointment:' . $row['id']]
      or fail("Missing CalDAV identity for appointment '{$row['id']}'.");
    $tree[$folder . "/" . filename($row['id']) . ".ics"] = \caldav\serialize($resource)
      ?? fail("Could not serialize appointment '{$row['id']}'.");
  }

  foreach(['contact' => 'contacts', 'organisation' => 'organisations'] as $type => $folder) {
    foreach(\carddav\book()[$folder] as $id => $row) {
      $resource = @$carddav["$type:$id"]
        or fail("Missing CardDAV identity for $type '$id'.");
      $tree["$folder/$id.vcf"] = \carddav\serialize($resource)
        ?? fail("Could not serialize $type '$id'.");
    }
  }

  foreach(\store\list_note_rows() as $row)
    $tree["notes/" . filename($row['id']) . ".md"] = note_markdown($row);
  foreach(\store\list_task_rows() as $row)
    $tree["todo/" . filename($row['id']) . ".md"] = task_markdown($row);
  foreach(\store\list_wish_rows() as $row)
    $tree["wishlist/" . filename($row['id']) . ".md"] = wish_markdown($row);
  foreach(\store\list_bookmark_rows() as $row)
    $tree["bookmarks/" . filename($row['id']) . ".md"] = bookmark_markdown($row);

  \caldav\forget();
  return $tree;
}

// The exporter's own bulk data, mirroring carddav's book: child rows for
// the markdown builders come from one query per table instead of a query
// per entity.

$_EXPORT_BOOK = null;

function book() {
  global $_EXPORT_BOOK;
  if($_EXPORT_BOOK !== null) return $_EXPORT_BOOK;

  $group = function($rows, $key) {
    $result = [];
    foreach($rows as $row) $result[$row[$key]][] = $row;
    return $result;
  };
  $tag_map = function($rows, $key) {
    $map = [];
    foreach($rows as $row) $map[$row[$key]][] = (int)$row['tag_id'];
    return $map;
  };

  $alarms = \store\list_all_alarms();

  return $_EXPORT_BOOK = [
    'note_tags' => $tag_map(\store\list_all_note_tags(), 'note_id'),
    'task_tags' => $tag_map(\store\list_all_task_tags(), 'task_id'),
    'wish_tags' => $tag_map(\store\list_all_wish_tags(), 'wish_id'),
    'bookmark_tags' => $tag_map(\store\list_all_bookmark_tags(), 'bookmark_id'),
    'timing_tags' => $tag_map(\store\list_all_timing_tags(), 'timing_id'),
    'task_logs' => $group(\store\list_all_task_logs(), 'task_id'),
    'wish_logs' => $group(\store\list_all_wish_logs(), 'wish_id'),
    'wish_urls' => $group(\store\list_all_wish_urls(), 'wish_id'),
    'task_alarms' => $group(array_filter($alarms, fn($alarm) => $alarm['task_id'] !== null), 'task_id'),
    'wish_alarms' => $group(array_filter($alarms, fn($alarm) => $alarm['wish_id'] !== null), 'wish_id'),
    'task_properties' => $group(\store\list_all_properties('task'), 'task_id'),
    'wish_properties' => $group(\store\list_all_properties('wish'), 'wish_id'),
    'alarm_properties' => $group(\store\list_all_properties('alarm'), 'alarm_id'),
  ];
}

function forget_book() {
  global $_EXPORT_BOOK;
  $_EXPORT_BOOK = null;
}

// External subscription ids can contain characters hostile to file paths;
// percent-encode anything outside the unreserved set.
function filename($id) {
  return rawurlencode((string)$id);
}

function json($value) {
  return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

function utc($value) {
  if($value === null) return null;
  return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone("UTC"))->format('Y-m-d\TH:i:s\Z');
}

function by_id($rows) {
  usort($rows, fn($a, $b) => $a['id'] <=> $b['id']);
  return $rows;
}

function property_data($row) {
  $data = [
    'name' => $row['name'],
    'parameters' => json_decode($row['parameters'], true) ?: [],
    'value' => $row['value'],
  ];
  if(@$row['group_name']) $data = ['group' => $row['group_name'], ...$data];
  return $data;
}

function properties_data($type, $id) {
  return array_map(property_data(...), @book()[$type . '_properties'][$id] ?: []);
}

function alarms_data($type, $id) {
  return array_map(fn($alarm) => [
    'id' => $alarm['id'],
    'trigger_at' => utc($alarm['trigger_at']),
    'trigger_offset' => $alarm['trigger_offset'] === null ? null : (int)$alarm['trigger_offset'],
    'relative_to' => $alarm['relative_to'],
    'description' => $alarm['description'],
    'properties' => properties_data('alarm', $alarm['id']),
  ], @book()[$type . '_alarms'][$id] ?: []);
}

// JSON files

function config_map() {
  $map = \store\config();
  ksort($map);
  return $map ?: new \stdClass();
}

function tags_data() {
  $properties = [];
  foreach(\store\list_all_properties('tag') as $row)
    $properties[$row['tag_id']][] = property_data($row);

  return array_map(fn($row) => [
    'id' => (int)$row['id'],
    'parent_id' => $row['parent_id'] === null ? null : (int)$row['parent_id'],
    'label' => $row['label'],
    'color' => $row['color'],
    'position' => (int)$row['position'],
    'properties' => @$properties[$row['id']] ?: [],
  ], \store\list_tag_rows());
}

function addresses_data() {
  return array_map(fn($row) => [
    'id' => $row['id'],
    'label' => $row['label'],
    'street_address' => $row['street_address'],
    'postal_code' => $row['postal_code'],
    'city' => $row['city'],
    'province' => $row['province'],
    'country' => $row['country'],
  ], by_id(\store\list_addresses()));
}

function calendars_data() {
  return array_map(fn($row) => [
    'id' => $row['id'],
    'title' => $row['title'],
    'subtitle' => $row['subtitle'],
    'color' => $row['color'],
    'position' => (int)$row['position'],
  ], by_id(\store\list_calendars()));
}

function subscriptions_data() {
  return array_map(fn($row) => [
    'id' => $row['id'],
    'title' => $row['title'],
    'subtitle' => $row['subtitle'],
    'url' => $row['url'],
    'color' => $row['color'],
    'filter' => $row['filter'],
    'history' => cast_bool($row['history']),
    'deduplicate' => cast_bool($row['deduplicate']),
    'position' => (int)$row['position'],
  ], by_id(\store\list_subscriptions()));
}

function habits_data() {
  $logs = [];
  foreach(\store\list_habit_logs() as $row)
    $logs[$row['habit_id']][] = $row['date'];

  return array_map(fn($row) => [
    'id' => $row['id'],
    'title' => $row['title'],
    'every' => $row['every'],
    'start_date' => $row['start_date'],
    'color' => $row['color'],
    'icon' => $row['icon'],
    'log' => @$logs[$row['id']] ?: [],
  ], by_id(\store\list_habits()));
}

function quotas_data() {
  return array_map(fn($row) => [
    'tag_id' => (int)$row['tag_id'],
    'period' => $row['period'],
    'minutes' => (int)$row['minutes'],
    'start_date' => $row['start_date'],
  ], \store\list_quota_rows());
}

function timings_by_month() {
  $months = [];
  foreach(\store\list_timing_rows() as $row) {
    $starts = utc($row['starts_at']);
    $months[substr($starts, 0, 7)][] = [
      'id' => $row['id'],
      'description' => $row['description'],
      'starts_at' => $starts,
      'ends_at' => utc($row['ends_at']),
      'task_id' => $row['task_id'],
      'tags' => @book()['timing_tags'][$row['id']] ?: [],
    ];
  }
  ksort($months);
  return $months;
}

// Markdown files

function note_markdown($row) {
  return document([
    'schema' => EXPORT_SCHEMA,
    'id' => $row['id'],
    'title' => $row['title'],
    'written_at' => utc($row['written_at']),
    'tags' => @book()['note_tags'][$row['id']] ?: [],
  ], $row['content']);
}

function task_markdown($row) {
  $log = @book()['task_logs'][$row['id']] ?: [];

  return document([
    'schema' => EXPORT_SCHEMA,
    'id' => $row['id'],
    'title' => $row['title'],
    'status' => @$log[count($log) - 1]['status'],
    'urgent' => cast_bool($row['urgent']),
    'recurrence' => $row['recurrence'],
    'open_at' => utc($row['open_at']),
    'due_at' => utc($row['due_at']),
    'due_all_day' => cast_bool($row['due_all_day']),
    'expire_at' => utc($row['expire_at']),
    'tags' => @book()['task_tags'][$row['id']] ?: [],
    'alarms' => alarms_data('task', $row['id']),
    'properties' => properties_data('task', $row['id']),
    'log' => status_log($log),
  ], $row['content']);
}

function wish_markdown($row) {
  $log = @book()['wish_logs'][$row['id']] ?: [];

  return document([
    'schema' => EXPORT_SCHEMA,
    'id' => $row['id'],
    'title' => $row['title'],
    'status' => @$log[count($log) - 1]['status'],
    'urgent' => cast_bool($row['urgent']),
    'added_at' => utc($row['added_at']),
    'urls' => array_map(fn($url) => [
      'url' => $url['url'],
      'price' => $url['price'],
    ], @book()['wish_urls'][$row['id']] ?: []),
    'tags' => @book()['wish_tags'][$row['id']] ?: [],
    'alarms' => alarms_data('wish', $row['id']),
    'properties' => properties_data('wish', $row['id']),
    'log' => status_log($log),
  ], $row['content']);
}

function bookmark_markdown($row) {
  return document([
    'schema' => EXPORT_SCHEMA,
    'id' => $row['id'],
    'label' => $row['label'],
    'url' => $row['url'],
    'favicon' => $row['favicon'],
    'saved_at' => utc($row['saved_at']),
    'tags' => @book()['bookmark_tags'][$row['id']] ?: [],
  ], $row['note']);
}

function status_log($log) {
  return array_map(fn($entry) => [
    'at' => utc($entry['changed_at']),
    'status' => $entry['status'],
    'comment' => cast_str($entry['comment'] ?? ''),
  ], $log);
}

function document($frontmatter, $body) {
  $text = "---\n" . yaml_map($frontmatter) . "---\n";
  $body = (string)$body;
  if($body !== "") {
    $text .= "\n" . $body;
    if(!str_ends_with($text, "\n")) $text .= "\n";
  }
  return $text;
}

// Deliberately small deterministic YAML encoder: maps keep their given key
// order, null values are omitted, scalars are double-quoted unless plainly
// safe.

function yaml_map($map, $indent = "") {
  $text = "";
  foreach($map as $key => $value) {
    if($value === null) continue;
    $text .= $indent . $key . ":" . yaml_value($value, $indent);
  }
  return $text;
}

function yaml_value($value, $indent) {
  if(is_array($value) && $value === []) return " []\n";

  if(is_array($value) && array_is_list($value)) {
    $text = "\n";
    foreach($value as $item) $text .= yaml_item($item, $indent . "  ");
    return $text;
  }

  if(is_array($value)) {
    if(!array_filter($value, fn($item) => $item !== null)) return " {}\n";
    return "\n" . yaml_map($value, $indent . "  ");
  }

  return " " . yaml_scalar($value) . "\n";
}

function yaml_item($item, $indent) {
  if(is_array($item)) {
    $text = yaml_map($item, $indent . "  ");
    return $indent . "-" . substr($text, strlen($indent) + 1);
  }
  return $indent . "- " . yaml_scalar($item) . "\n";
}

function yaml_scalar($value) {
  if(is_bool($value)) return $value ? "true" : "false";
  if(is_int($value) || is_float($value)) return (string)$value;

  $value = (string)$value;
  $plain = preg_match('/^[A-Za-z0-9][A-Za-z0-9 _.,\/+@-]*$/', $value)
    && !preg_match('/^(true|false|null|yes|no|on|off)$/i', $value)
    && !is_numeric($value)
    && !str_ends_with($value, " ");
  if($plain) return $value;

  $escaped = "";
  foreach(mb_str_split($value) as $char) {
    $escaped .= match(true) {
      $char == "\\" => "\\\\",
      $char == "\"" => "\\\"",
      $char == "\n" => "\\n",
      $char == "\r" => "\\r",
      $char == "\t" => "\\t",
      strlen($char) == 1 && ord($char) < 0x20 => sprintf('\\x%02x', ord($char)),
      default => $char,
    };
  }
  return "\"$escaped\"";
}

bracket_request([
  'guard' => guard(...),
  'before_commit' => before_commit(...),
  'after_commit' => after_commit(...),
  'abandon' => abandon(...),
]);
