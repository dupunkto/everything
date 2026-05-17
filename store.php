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

// Tracker

function put_timing($id, $description, $starts_at, $ends_at, $task_id) {
  if($task_id) get_task($task_id) or die("task with ID $task_id does not exist");

  return exec_query('INSERT INTO `timings` (
    `id`, 
    `description`,
    `starts_at`,
    `ends_at`,
    `task_id`
  ) VALUES (?, ?, ?, ?, ?)', [
    $id,
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

function list_timings() {
  return all('SELECT * FROM `timings` ORDER BY `starts_at` DESC');
}

function get_timing($id) {
  return one('SELECT * FROM `timings` WHERE `id` = ?', [$id]);
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
