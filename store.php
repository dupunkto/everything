<?php
// SQL-based store.

namespace store;

$_URL = getenv("DATABASE_URL") ?: "sqlite://data.db";

if(str_starts_with($_URL, "sqlite://")) {
  $_DATABASE = ['scheme' => 'sqlite', 'path' => substr($_URL, strlen("sqlite://"))];
}
else {
  $_DATABASE = parse_url($_URL) or die("Syntax error in database connection string.");
}

switch($_DATABASE['scheme']) {
  case 'mysql': require __DIR__ . "/store/adapter/mysql.php"; break;
  case 'postgres': require __DIR__ . "/store/adapter/postgres.php"; break;
  case 'sqlite': require __DIR__ . "/store/adapter/sqlite.php"; break;
}

// Tasks

function create_task(
  $title,
  $content,
  $urgent = false,
  $recurrence = null,
  $open_date = null,
  $due_date = null,
  $expiration_date = null
) {
  $open_date ??= gmdate("Y-m-d H:i:s");

  return exec_query('INSERT INTO `tasks` (
    `id`, 
    `title`,
    `content`,
    `urgent`,
    `recurrence`,
    `open_date`,
    `due_date`,
    `expiration_date`
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
    generate_humid(),
    $title,
    $content,
    $urgent,
    $recurrence,
    $open_date,
    $due_date,
    $expiration_date
  ]);
}

function update_task(
  $id,
  $title,
  $content,
  $urgent,
  $recurrence,
  $open_date,
  $due_date,
  $expiration_date
) {
  return exec_query('UPDATE `tasks` SET
    `title` = ?,
    `content` = ?,
    `urgent` = ?,
    `recurrence` = ?,
    `open_date` = ?,
    `due_date` = ?,
    `expiration_date` = ?
  WHERE id = ?', [
    $id,
    $title,
    $content,
    $urgent,
    $recurrence,
    $open_date,
    $due_date,
    $expiration_date,
    $id
  ]);
}

define('STATUSSES', ["todo", "backlog", "blocked", "done", "nvm"]);

function set_task_status($id, $status, $comment) {
  in_array($status, STATUSSES) or die("status $status does not exist");

  return exec_query('INSERT INTO `task_log` (
    `task_id`,
    `status`,
    `comment`
  ) VALUES (?, ?, ?)', [
    $id,
    $status,
    $comment
  ]);
}

function add_task_tag($id, $tag_id) {
  return exec_query('INSERT INTO `tasks_tags` (
    `task_id`, `tag_id`) VALUES (?, ?)', [$id, $tag_id]);
}

function remove_task_tag($id, $tag_id) {
  return exec_query('DELETE FROM `tasks_tags`
    WHERE `task_id` = ? AND `tag_id` = ?', [$id, $tag_id]);
}

function list_tasks($query = "") {
  $include_statuses = [];
  $exclude_statuses = [];
  $selectors = [];

  foreach(explode(" ", $query) as $segment) {
    $parts = explode(":", $segment);
    if(count($parts) != 2) continue;
    [$selector, $value] = $parts;

    if($selector == "is" && $value == 'urgent') $selectors[] = "`urgent` = true";
    if($selector == "not" && $value == 'urgent') $selectors[] = "`urgent` = false";
    if($key == "is" && in_array($value, STATUSES)) $include_statuses[] = "`status` = '$value'";
    if($key == "not" && in_array($value, STATUSES)) $exclude_statuses[] = "`status` != '$value'";
  }

  if($include_statuses) $selectors[] = "(" . implode(" OR ", $include_statuses) . ")";
  if($exclude_statuses) $selectors[] = "(" . implode(" AND ", $exclude_statuses) . ")";

  $where_clause = $selectors == [] ? "" : "WHERE " . implode(" OR ", $selectors);

  return all("SELECT * FROM `tasks`
    $where_clause ORDER BY
      `due_date` NULLS LAST, `expiration_date` NULLS LAST, `initial_date`");
}

function get_task($id) {
  $task = one('SELECT
    task.id,
    task.title,
    task.content,
    task.urgent,
    task.recurrence,
    task.open_date,
    task.due_date,
    task.expiration_date,
    log.status,
    log.comment,
    log.date as updated_date
  FROM tasks task
  LEFT JOIN task_log log ON log.task_id = task.id
  WHERE task.id = ?
  ORDER BY log.date DESC', [$id]);

  if($task == null) return $task;

  $tags = all('SELECT tags.label FROM `tags` WHERE `task_id` = ?', [$id]);

  if($tags == null) return $tags;

  $task['tags'] = array_column($tags, 'label');

  return $task;
}

function delete_task($id) {
  return exec_query('DELETE FROM `tasks` WHERE `id` = ?', [$id]);
}

// Tracker

function create_timing($id, $description, $starts_at, $ends_at, $task_id) {
  if($task_id) get_task($task_id) or die("task with ID $task_id does not exist");

  return exec_query('INSERT INTO `timings` (
    `id`, 
    `description`,
    `starts_at`,
    `ends_at`,
    `task_id`
  ) VALUES (?, ?, ?, ?, ?)', [
    generate_humid(),
    $description,
    $starts_at,
    $ends_at,
    $task_id
  ]);
}

function update_timing($id, $description, $starts_at, $ends_at, $task_id) {
  if($task_id) get_task($task_id) or die("task with ID $task_id does not exist");

  return exec_query('UPDATE `timings` SET
    `description` = ?,
    `starts_at` = ?,
    `ends_at` = ?,
    `task_id` = ?
  WHERE id = ?', [
    $description,
    $starts_at,
    $ends_at,
    $task_id,
    $id
  ]);
}

function add_timing_tag($id, $tag_id) {
  return exec_query('INSERT INTO `timings_tags` (
    `timing_id`, `tag_id`) VALUES (?, ?)', [$id, $tag_id]);
}

function remove_timing_tag($id, $tag_id) {
  return exec_query('DELETE FROM `timings_tags`
    WHERE `timing_id` = ? AND `tag_id` = ?', [$id, $tag_id]);
}

function list_timings() {
  return all('SELECT * FROM `timings` ORDER BY `starts_at` DESC');
}

function get_timing($id) {
  return one('SELECT * FROM `timings` WHERE `id` = ?', [$id]);
}

function delete_timing($id) {
  return exec_query('DELETE FROM `timings` WHERE `id` = ?', [$id]);
}

// Tags

function create_tag($label, $color, $parent_id) {
  if($parent_id) get_tag($parent_id) or die("tag with ID $parent_id does not exist");

  return exec_query('INSERT INTO `tags` (
    `label`,
    `color`,
    `parent_id`
  ) VALUES (?, ?, ?)', [
    $label,
    $color,
    $parent_id
  ]);
}

function update_tag($id, $label, $color, $parent_id) {
  if($parent_id) {
    $cursor = $parent_id;
    while($cursor) {
      if($cursor == $id) die("illegal circular structure detected");
      $tag = get_tag($cursor) or die("tag with ID $cursor does not exist");
      $cursor = $tag['parent_id'];
    }
  }

  return exec_query('UPDATE `tags` SET
    `label` = ?,
    `color` = ?,
    `parent_id` = ?
  WHERE id = ?', [
    $label,
    $color,
    $parent_id,
    $id
  ]);
}

function list_tags() {
  $tags = all('SELECT * FROM `tags` ORDER BY `id` DESC');

  $children = [];
  foreach($tags as $tag) $children[$tag['parent_id']][] = $tag;

  $result = [];
  $walk = function($parent_id) use (&$walk, &$children, &$result) {
    foreach($children[$parent_id] ?? [] as $tag) {
      $result[] = $tag;
      $walk($tag['id']);
    }
  };

  $walk(null);
  return $result;
}

function get_tag($id) {
  return one('SELECT * FROM `tags` WHERE `id` = ?', [$id]);
}

function get_tag_by_label($label) {
  return one('SELECT * FROM `tags` WHERE `label` = ?', [$label]);
}

function delete_tag($id) {
  return exec_query('DELETE FROM `tags` WHERE `id` = ?', [$id]);
}

// Configuration

function config() {
  $map = [];
  $rows = all("SELECT * FROM `config`");

  foreach($rows as $row)
    $map[$row['property']] = $row['value'];

  return $map;
}

// Migrations

function version() {
  $latest = one('SELECT * FROM `migrations` ORDER BY `version` DESC');
  return @$latest['version'] ?? -1;
}

function seed() {
  \adapter\execute(__DIR__ . "/store/seeds.sql");
}

function migrate($from, $to) {
  if($from == $to) return; // Skip migrations altogether if store is up-to-date.
  syslog(LOG_INFO, "Running migrations for version: " . $to);

  $pending = [];
  $migrations = glob(__DIR__ . "/store/migrations/v*.sql") ?: [];

  foreach ($migrations as $path) {
    $version = (int)substr(basename($path), 1); // The int cast stops at '_'.
    if ($version > $from && $version <= $to) $pending[$version] = $path;
  }

  ksort($pending, SORT_NUMERIC);

  foreach ($pending as $version => $path) {
    syslog(LOG_INFO, "Migrating store schema to v$version");
    \adapter\execute($path);

    // NOTE(robin): if the STORE_VERSION value is higher than any migration file
    // (aka the migration file has not been committed or is missing), this function
    // will run on EVERY REQUEST, because the database never catches up. Bad?
    exec_query('INSERT INTO `migrations` (`version`) VALUES (?)', [$version])
      or die("Failed to bump store version to v" . $version . ".");
  }
}

// Uniqueness

function unique_slug($table, $seed) {
  $slug = slugify($seed);
  $num = 1;
  $try = $slug;
  while(slug_taken($table, $try)) $try = $slug . "-" . $num++;
  return $try;
}

function slug_taken($table, $slug) {
  return !!one("SELECT slug FROM `$table` WHERE slug = ?", [$slug]);
}

// SQL helpers

function one($sql, $params = []) {
  return exec_query("$sql LIMIT 1", $params)?->fetch();
}

function all($sql, $params = []) {
  return exec_query($sql, $params)?->fetchAll();
}

function exec_query($sql, $params) {
  try {
    $query = DBH->prepare($sql);
    $query->execute($params);
    return $query;
  }
  catch(\PDOException $e) {
    trigger_error($e, E_USER_WARNING);
    return null;
  }
}

// Initialize database connection

define('DBH', \adapter\establish_connection());

if(!defined('INITIAL_RUN')) {
  define('INITIAL_RUN', \adapter\initial_run());
}

// Run migrations on the connected SQL database,
// and insert seed data when initializing database.

$latest_store_version = STORE_VERSION;
$current_store_version = INITIAL_RUN ? -1 : version();

if($current_store_version > $latest_store_version) {
  die("Mismatched store versions: expected v" . STORE_VERSION . ", 
  but store is already at v$version");
}

if($current_store_version < $latest_store_version) {
  migrate(from: $current_store_version, to: $latest_store_version);
}

if(INITIAL_RUN) seed();
