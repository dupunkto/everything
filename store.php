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

function create_timing($description, $starts_at, $ends_at, $task_id = null) {
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

// Calendars

function create_calendar($title, $subtitle, $color) {
  return exec_query('INSERT INTO `calendars` (
    `id`,
    `title`,
    `subtitle`,
    `color`
  ) VALUES (?, ?, ?, ?)', [
    generate_humid(),
    $title,
    $subtitle,
    $color
  ]);
}

function update_calendar($id, $title, $subtitle, $color) {
  return exec_query('UPDATE `calendars` SET
    `title` = ?,
    `subtitle` = ?,
    `color` = ?
  WHERE id = ?', [$title, $subtitle, $color, $id]);
}

function list_calendars() {
  return all('SELECT * FROM `calendars` ORDER BY `title`');
}

function get_calendar($id) {
  return one('SELECT * FROM `calendars` WHERE `id` = ?', [$id]);
}

function delete_calendar($id) {
  return exec_query('DELETE FROM `calendars` WHERE `id` = ?', [$id]);
}

// Subscriptions

function create_subscription($title, $subtitle, $url, $color) {
  return exec_query('INSERT INTO `subscriptions` (
    `id`,
    `title`,
    `subtitle`,
    `url`,
    `color`
  ) VALUES (?, ?, ?, ?, ?)', [
    generate_humid(),
    $title,
    $subtitle,
    $url,
    $color
  ]);
}

function update_subscription($id, $title, $subtitle, $url, $color) {
  return exec_query('UPDATE `subscriptions` SET
    `title` = ?,
    `subtitle` = ?,
    `url` = ?,
    `color` = ?
  WHERE id = ?', [$title, $subtitle, $url, $color, $id]);
}

function list_subscriptions() {
  return all('SELECT * FROM `subscriptions` ORDER BY `title`');
}

function get_subscription($id) {
  return one('SELECT * FROM `subscriptions` WHERE `id` = ?', [$id]);
}

function delete_subscription($id) {
  return exec_query('DELETE FROM `subscriptions` WHERE `id` = ?', [$id]);
}

// Appointments

function create_appointment(
  $calendar_id,
  $title,
  $content,
  $starts_at,
  $ends_at,
  $location = null,
  $meeting = null,
  $recurrence = null,
  $all_day = false,
  $going = true,
  $circled = false,
  $color = null,
) {
  return exec_query('INSERT INTO `appointments` (
    `id`,
    `calendar_id`,
    `subscription_id`,
    `title`,
    `content`,
    `starts_at`,
    `ends_at`,
    `location`,
    `meeting`,
    `recurrence`,
    `all_day`,
    `going`,
    `circled`,
    `color`
  ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    generate_humid(),
    $calendar_id,
    $title,
    $content,
    $starts_at,
    $ends_at,
    $location,
    $meeting,
    $recurrence,
    $all_day ? 1 : 0,
    $going ? 1 : 0,
    $circled ? 1 : 0,
    $color
  ]);
}

function update_appointment(
  $id,
  $title,
  $content,
  $starts_at,
  $ends_at,
  $location = null,
  $meeting = null,
  $recurrence = null,
  $all_day = false,
  $going = true,
  $circled = false,
  $color = null,
) {
  // Subscription-owned events should only flip the user-managed annotation
  // fields (color, going, circled). Mirroring fields like title/starts_at
  // belongs to the sync agent, which overwrites them on every poll.
  return exec_query('UPDATE `appointments` SET
    `title` = ?,
    `content` = ?,
    `starts_at` = ?,
    `ends_at` = ?,
    `location` = ?,
    `meeting` = ?,
    `recurrence` = ?,
    `all_day` = ?,
    `going` = ?,
    `circled` = ?,
    `color` = ?
  WHERE id = ?', [
    $title,
    $content,
    $starts_at,
    $ends_at,
    $location,
    $meeting,
    $recurrence,
    $all_day ? 1 : 0,
    $going ? 1 : 0,
    $circled ? 1 : 0,
    $color,
    $id
  ]);
}

function update_appointment_annotations($id, $color, $going, $circled) {
  return exec_query('UPDATE `appointments` SET
    `color` = ?,
    `going` = ?,
    `circled` = ?
  WHERE id = ?', [$color, $going ? 1 : 0, $circled ? 1 : 0, $id]);
}

function list_appointments_between($from, $to) {
  return all('SELECT
    a.*,
    c.title AS calendar_title,
    c.subtitle AS calendar_subtitle,
    c.color AS calendar_color,
    s.title AS subscription_title,
    s.subtitle AS subscription_subtitle,
    s.color AS subscription_color
  FROM `appointments` a
  LEFT JOIN `calendars` c ON c.id = a.calendar_id
  LEFT JOIN `subscriptions` s ON s.id = a.subscription_id
  WHERE a.starts_at < ? AND a.ends_at > ?
  ORDER BY a.starts_at', [$to, $from]);
}

function get_appointment($id) {
  return one('SELECT
    a.*,
    c.title AS calendar_title,
    c.subtitle AS calendar_subtitle,
    c.color AS calendar_color,
    s.title AS subscription_title,
    s.subtitle AS subscription_subtitle,
    s.color AS subscription_color
  FROM `appointments` a
  LEFT JOIN `calendars` c ON c.id = a.calendar_id
  LEFT JOIN `subscriptions` s ON s.id = a.subscription_id
  WHERE a.id = ?', [$id]);
}

function delete_appointment($id) {
  return exec_query('DELETE FROM `appointments` WHERE `id` = ?', [$id]);
}

// WIP

// Probes an iCal URL to validate it and extract the calendar's display name
// and (best-effort) color from VCALENDAR-level properties. Returns null on
// invalid/unreachable feeds, otherwise ['title' => ..., 'color' => ...|null].
//
// NOTE(robin): the iCal sync agent will likely consolidate this with the
// full event parser; treat this as a stopgap header probe.
function probe_ical_feed($url) {
  $response = \http\get($url);
  if($response['state'] != "success" or $response['status'] >= 400) return null;

  $body = $response['body'];
  if(!str_contains($body, "BEGIN:VCALENDAR")) return null;

  // RFC 5545 lets long lines be folded across multiple physical lines using
  // a CRLF + leading whitespace. Unfold before scanning.
  $unfolded = preg_replace("/\r?\n[ \t]/", "", $body);
  $lines = preg_split("/\r?\n/", $unfolded);

  $title = null;
  $color = null;

  foreach($lines as $line) {
    if(str_starts_with($line, "BEGIN:VEVENT")) break; // header-only probe

    // Property names can carry parameters (after ';'). Strip them.
    [$head, $value] = explode(":", $line, 2) + [null, null];
    if($value === null) continue;
    $name = strtoupper(explode(";", $head)[0]);

    if($name == "X-WR-CALNAME" and !$title) $title = trim($value);
    if($name == "X-APPLE-CALENDAR-COLOR" and !$color) $color = trim($value);
    if($name == "COLOR" and !$color) $color = css_named_to_hex(trim($value));
  }

  if(!$title) {
    $host = parse_url($url, PHP_URL_HOST);
    $title = $host ?: "Untitled subscription";
  }

  return ["title" => $title, "color" => $color];
}

// A tiny CSS named-color → hex map for the subset RFC 7986 expects (the
// CSS3 extended palette). Returns null if the name is unrecognised.
function css_named_to_hex($name) {
  static $map = [
    "black" => "#000000", "silver" => "#c0c0c0", "gray" => "#808080",
    "white" => "#ffffff", "maroon" => "#800000", "red" => "#ff0000",
    "purple" => "#800080", "fuchsia" => "#ff00ff", "green" => "#008000",
    "lime" => "#00ff00", "olive" => "#808000", "yellow" => "#ffff00",
    "navy" => "#000080", "blue" => "#0000ff", "teal" => "#008080",
    "aqua" => "#00ffff", "orange" => "#ffa500", "pink" => "#ffc0cb",
    "cyan" => "#00ffff", "magenta" => "#ff00ff", "indigo" => "#4b0082",
    "violet" => "#ee82ee", "gold" => "#ffd700", "coral" => "#ff7f50",
    "tomato" => "#ff6347", "salmon" => "#fa8072", "khaki" => "#f0e68c",
  ];
  return $map[strtolower($name)] ?? null;
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
