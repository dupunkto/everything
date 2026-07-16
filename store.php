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

define('ENUM_TASK_STATUS', ['todo', 'backlog', 'blocked', 'done', 'nvm']);
define('ENUM_WISH_STATUS', ['dream', 'bought', 'nvm']);

// Notes

function create_note($title, $content) {
  return exec_query('INSERT INTO `notes` (
    `id`,
    `title`,
    `content`
  ) VALUES (?, ?, ?)', [
    generate_humid(),
    $title,
    $content
  ]);
}

function update_note($id, $title, $content) {
  return exec_query('UPDATE `notes` SET
    `title` = ?,
    `content` = ?
  WHERE id = ?', [$title, $content, $id]);
}

function get_note($id) {
  return one('SELECT * FROM `notes` WHERE `id` = ?', [$id]);
}

function list_notes($query = "") {
  $where = [];
  $params = [];
  $tags = [];

  foreach(preg_split('/\s+/', trim($query)) ?: [] as $token) {
    if($token == "") continue;

    if($token[0] == "+") {
      $tags[] = mb_strtolower(substr($token, 1));
      continue;
    }

    $where[] = '(LOWER(`title`) LIKE ? OR LOWER(`content`) LIKE ?)';
    $params[] = "%" . mb_strtolower($token) . "%";
    $params[] = "%" . mb_strtolower($token) . "%";
  }

  foreach(array_filter($tags) as $tag) {
    $where[] = 'EXISTS (
      SELECT 1 FROM `notes_tags` nt
      JOIN `tags` t ON t.id = nt.tag_id
      WHERE nt.note_id = notes.id AND LOWER(t.label) = ?
    )';
    $params[] = $tag;
  }

  $sql = 'SELECT * FROM `notes`';
  if($where) $sql .= ' WHERE ' . join(' AND ', $where);
  $sql .= ' ORDER BY `title`';

  return all($sql, $params) ?? [];
}

function delete_note($id) {
  return exec_query('DELETE FROM `notes` WHERE `id` = ?', [$id]);
}

// Tasks

function create_task(
  $title,
  $content,
  $status,
  $urgent = false,
  $recurrence = null,
  $open_date = null,
  $due_date = null,
  $due_all_day = false,
  $expiration_date = null,
  $comment = null
) {
  in_array($status, ENUM_TASK_STATUS) or die("status $status does not exist");

  $open_date ??= gmdate('c');

  $ok = exec_query('INSERT INTO `tasks` (
    `id`,
    `title`,
    `content`,
    `urgent`,
    `recurrence`,
    `open_date`,
    `due_date`,
    `due_all_day`,
    `expiration_date`
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $title,
    $content,
    $urgent,
    $recurrence,
    $open_date,
    $due_date,
    $due_all_day,
    $expiration_date
  ]);

  if(!$ok) return $ok;

  $ok = exec_query('INSERT INTO `task_log` (
    `task_id`,
    `date`,
    `status`,
    `comment`
  ) VALUES (?, ?, ?, ?)', [
    $id,
    gmdate('c'),
    $status,
    $comment
  ]);

  return $ok;
}

function update_task(
  $id,
  $title,
  $content,
  $urgent,
  $recurrence,
  $open_date,
  $due_date,
  $due_all_day,
  $expiration_date
) {
  return exec_query('UPDATE `tasks` SET
    `title` = ?,
    `content` = ?,
    `urgent` = ?,
    `recurrence` = ?,
    `open_date` = ?,
    `due_date` = ?,
    `due_all_day` = ?,
    `expiration_date` = ?
  WHERE id = ?', [
    $title,
    $content,
    $urgent,
    $recurrence,
    $open_date,
    $due_date,
    $due_all_day,
    $expiration_date,
    $id
  ]);
}

function set_task_status($id, $status, $comment = "") {
  in_array($status, ENUM_TASK_STATUS) or die("status $status does not exist");

  // Skip redundant transitions: if the latest entry already has this status and
  // no comment is being added, there is nothing new to record. This stops rapid
  // toggles from the listing (which race the async re-render) from stacking up
  // empty log events.
  $latest = one('SELECT `status` FROM `task_log`
    WHERE `task_id` = ? ORDER BY `date` DESC, `id` DESC', [$id]);
  if($latest && $latest['status'] === $status && !$comment) return true;

  return exec_query('INSERT INTO `task_log` (
    `task_id`,
    `date`,
    `status`,
    `comment`
  ) VALUES (?, ?, ?, ?)', [
    $id,
    gmdate('c'),
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

// Status is derived from the log (see \recurrence\task_state) and a recurring
// task's effective status can differ from its latest log row, so filtering
// happens in PHP rather than SQL. $override lists ids that bypass the filter
// entirely (freshly clicked tasks that shouldn't vanish under the cursor).
// $respect_horizon hides recurring tasks outside their visibility window; the
// calendar passes false to keep future deadlines addressable.
function list_tasks($query = "", $override = [], $respect_horizon = true) {
  $include = [];
  $exclude = [];
  $urgent = null;

  foreach(explode(" ", $query) as $segment) {
    $parts = explode(":", $segment);
    if(count($parts) != 2) continue;
    [$selector, $value] = $parts;

    if($value == 'urgent') $urgent = $selector == "is";
    elseif($selector == "is" && in_array($value, ENUM_TASK_STATUS)) $include[] = $value;
    elseif($selector == "not" && in_array($value, ENUM_TASK_STATUS)) $exclude[] = $value;
  }

  $rows = all("SELECT
      tasks.*,
      tags.id as tag_id,
      tags.label as tag_label,
      tags.color as tag_color,
      tags.parent_id as tag_parent_id,
      log.status,
      log.comment,
      log.date as updated_date,
      (SELECT done.date FROM `task_log` done
        WHERE done.task_id = tasks.id AND done.status = 'done'
        ORDER BY done.date DESC, done.id DESC LIMIT 1) AS last_done
    FROM `tasks`
    LEFT JOIN `task_log` log
      ON log.id = (
        SELECT ranked.id
        FROM `task_log` ranked
        WHERE ranked.task_id = tasks.id
        ORDER BY ranked.date DESC, ranked.id DESC
        LIMIT 1
      )
    LEFT JOIN `tasks_tags` tt ON tt.task_id = tasks.id
    LEFT JOIN `tags` ON tags.id = tt.tag_id
    ORDER BY
      `due_date` NULLS LAST,
      `expiration_date` NULLS LAST,
      `open_date`");

  if(!$rows) return $rows;

  $override = $override ?? [];
  $result = [];

  foreach(collect_by($rows, 'tag', 'tags') as $task) {
    $state = \recurrence\task_state($task);
    $task = array_merge($task, $state);

    if(in_array($task['id'], $override, true)) { $result[] = $task; continue; }

    if($respect_horizon && !$state['visible']) continue;
    if($urgent !== null && filter_var($task['urgent'], FILTER_VALIDATE_BOOLEAN) != $urgent) continue;
    if($include && !in_array($state['status'], $include)) continue;
    if($exclude && in_array($state['status'], $exclude)) continue;

    $result[] = $task;
  }

  return $result;
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
    task.due_all_day,
    task.expiration_date,
    log.status,
    log.comment,
    log.date as updated_date,
    (SELECT done.date FROM `task_log` done
      WHERE done.task_id = task.id AND done.status = \'done\'
      ORDER BY done.date DESC, done.id DESC LIMIT 1) AS last_done
  FROM `tasks` task
  LEFT JOIN `task_log` log ON log.task_id = task.id
  WHERE task.id = ?
  ORDER BY log.date DESC', [$id]);

  if($task === false) return $task;

  $task = array_merge($task, \recurrence\task_state($task));

  $tags = all('SELECT tags.label FROM `tags`
    JOIN `tasks_tags` tt ON tt.tag_id = tags.id
    WHERE tt.task_id = ?', [$id]);

  if($tags === false) return $tags;

  $task['tags'] = array_column($tags, 'label');

  return $task;
}

function get_task_log($id) {
  return all('SELECT * FROM `task_log` WHERE `task_id` = ? ORDER BY `date` ASC', [$id]) ?? [];
}

function delete_task($id) {
  return exec_query('DELETE FROM `tasks` WHERE `id` = ?', [$id]);
}

// Wishes

function create_wish($title, $content, $status, $urgent = false) {
  in_array($status, ENUM_WISH_STATUS) or die("status $status does not exist");

  $ok = exec_query('INSERT INTO `wishes` (
    `id`,
    `title`,
    `content`,
    `urgent`
  ) VALUES (?, ?, ?, ?)', [
    $id = generate_humid(),
    $title,
    $content,
    $urgent
  ]);

  if(!$ok) return $ok;

  $ok = exec_query('INSERT INTO `wish_log` (
    `wish_id`,
    `status`
  ) VALUES (?, ?)', [$id, $status]);

  return $ok ? $id : $ok;
}

function set_wish_status($id, $status, $comment = "") {
  in_array($status, ENUM_WISH_STATUS) or die("status $status does not exist");

  // Skip redundant transitions (see set_task_status): the wish listing has the
  // same toggle, so rapid clicks would otherwise stack up empty log events.
  $latest = one('SELECT `status` FROM `wish_log`
    WHERE `wish_id` = ? ORDER BY `date` DESC, `id` DESC', [$id]);
  if($latest && $latest['status'] === $status && !$comment) return true;

  return exec_query('INSERT INTO `wish_log` (
    `wish_id`,
    `status`,
    `comment`
  ) VALUES (?, ?, ?)', [$id, $status, $comment]);
}

function update_wish($id, $title, $content, $urgent) {
  return exec_query('UPDATE `wishes` SET
    `title` = ?,
    `content` = ?,
    `urgent` = ?
  WHERE id = ?', [$title, $content, $urgent, $id]);
}

function get_wish($id) {
  $wish = one('SELECT
    wishes.*,
    log.status,
    log.comment,
    log.date as updated_date
  FROM `wishes`
  LEFT JOIN `wish_log` log
    ON log.id = (
      SELECT ranked.id
      FROM `wish_log` ranked
      WHERE ranked.wish_id = wishes.id
      ORDER BY ranked.date DESC, ranked.id DESC
      LIMIT 1
    )
  WHERE wishes.id = ?', [$id]);

  if(!$wish) return $wish;

  $wish['urls'] = list_wish_urls($id);

  return $wish;
}

function list_wish_urls($id) {
  return all('SELECT * FROM `wish_urls` WHERE wish_id = ?', [$id]);
}

function set_wish_urls($id, $rows) {
  set_children('wish_urls', 'wish_id', $id, $rows);
}

function get_wish_log($id) {
  return all('SELECT * FROM `wish_log` WHERE `wish_id` = ? ORDER BY `date` ASC', [$id]) ?? [];
}

function list_wishes($statuses = [], $override = []) {
  $rows = all("SELECT
      wishes.*,
      log.status,
      log.comment,
      log.date as updated_date,
      (SELECT SUM(price) FROM `wish_urls` WHERE wish_urls.wish_id = wishes.id) as total_price
    FROM `wishes`
    LEFT JOIN `wish_log` log
      ON log.id = (
        SELECT ranked.id
        FROM `wish_log` ranked
        WHERE ranked.wish_id = wishes.id
        ORDER BY ranked.date DESC, ranked.id DESC
        LIMIT 1
      )
    ORDER BY `title`");

  if(!$rows) return $rows;

  $result = [];

  foreach($rows as $wish) {
    if(in_array($wish['id'], $override, true)) { $result[] = $wish; continue; }
    if($statuses && !in_array($wish['status'], $statuses)) continue;

    $result[] = $wish;
  }

  return $result;
}

function delete_wish($id) {
  return exec_query('DELETE FROM `wishes` WHERE `id` = ?', [$id]);
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

// Timings overlapping [$from, $to), each carrying its first tag (lowest id).
// The caller resolves that tag to its root to pick a colour; keeping it a bare
// id keeps this query portable across the SQL engines we target.
function list_timings_between($from, $to) {
  return all('SELECT
    t.*,
    (SELECT tt.tag_id FROM `timings_tags` tt
      WHERE tt.timing_id = t.id
      ORDER BY tt.tag_id ASC LIMIT 1) AS first_tag_id
  FROM `timings` t
  WHERE t.starts_at < ? AND t.ends_at > ?
  ORDER BY t.starts_at', [$to, $from]);
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
    `parent_id`,
    `order`
  ) VALUES (?, ?, ?, ?)', [
    $label,
    $color,
    $parent_id,
    prepend_order('tags')
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
  $tags = all('SELECT * FROM `tags` ORDER BY `order` ASC, `id` DESC');

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

function reorder_tags($ids) {
  foreach(array_values($ids) as $order => $id) {
    $ok = exec_query('UPDATE `tags` SET `order` = ? WHERE `id` = ?', [$order, $id]);
    if(!$ok) return false;
  }

  return true;
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

// Ordering

function prepend_order($table) {
  $first = one('SELECT MIN(`order`) AS `order` FROM ' . $table);

  if($first['order'] === false) return 0;
  if($first['order'] > 0) return $first['order'] - 1;

  // If this fails, too bad, the ordering is a bit messed up, but
  // not the end of the world.
  exec_query('UPDATE ' . $table . ' SET `order` = `order` + 1', []);
  
  return 0;
}

function append_order($table) {
  $last = one('SELECT COALESCE(MAX(`order`), 0) AS `order` FROM ' . $table);
  return $last['order'] + 1;
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

// The oldest calendar, by insertion order. Backs the default-calendar config
// fallback so a fresh install still has somewhere to drop new events.
function first_calendar_id() {
  return @one('SELECT `id` FROM `calendars` ORDER BY `rowid`')['id'];
}

function delete_calendar($id) {
  return exec_query('DELETE FROM `calendars` WHERE `id` = ?', [$id]);
}

// Subscriptions

function create_subscription($title, $subtitle, $url, $color, $filter = null) {
  return exec_query('INSERT INTO `subscriptions` (
    `id`,
    `title`,
    `subtitle`,
    `url`,
    `color`,
    `filter`
  ) VALUES (?, ?, ?, ?, ?, ?)', [
    generate_humid(),
    $title,
    $subtitle,
    $url,
    $color,
    $filter
  ]);
}

function update_subscription($id, $title, $subtitle, $url, $color, $filter = null) {
  return exec_query('UPDATE `subscriptions` SET
    `title` = ?,
    `subtitle` = ?,
    `url` = ?,
    `color` = ?,
    `filter` = ?
  WHERE id = ?', [$title, $subtitle, $url, $color, $filter, $id]);
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

// Habits

function create_habit($title, $every, $color, $icon) {
  return exec_query('INSERT INTO `habits` (
    `id`,
    `title`,
    `every`,
    `color`,
    `icon`
  ) VALUES (?, ?, ?, ?, ?)', [
    generate_humid(),
    $title,
    $every,
    $color,
    $icon
  ]);
}

function update_habit($id, $title, $every, $color, $icon) {
  return exec_query('UPDATE `habits` SET
    `title` = ?,
    `every` = ?,
    `color` = ?,
    `icon` = ?
  WHERE id = ?', [$title, $every, $color, $icon, $id]);
}

function list_habits() {
  return all('SELECT * FROM `habits` ORDER BY `title`');
}

function get_habit($id) {
  return one('SELECT * FROM `habits` WHERE `id` = ?', [$id]);
}

function delete_habit($id) {
  return exec_query('DELETE FROM `habits` WHERE `id` = ?', [$id]);
}

function list_habit_logs($from, $to) {
  return all('SELECT `habit_id`, DATE(`date`) AS `date`
    FROM `habit_log`
    WHERE `date` >= ? AND `date` < ?
    ORDER BY `date`', [$from, $to]);
}

function get_habit_log($habit_id, $date) {
  return one('SELECT * FROM `habit_log`
    WHERE `habit_id` = ? AND DATE(`date`) = ?', [$habit_id, $date]);
}

function log_habit($habit_id, $date) {
  if(get_habit_log($habit_id, $date)) return true;

  $id = @one('SELECT MAX(`id`) + 1 AS `id` FROM `habit_log`')['id'] ?: 1;

  return exec_query('INSERT INTO `habit_log` (
    `id`,
    `habit_id`,
    `date`
  ) VALUES (?, ?, ?)', [$id, $habit_id, "$date 00:00:00"]);
}

function unlog_habit($habit_id, $date) {
  return exec_query('DELETE FROM `habit_log`
    WHERE `habit_id` = ? AND DATE(`date`) = ?', [$habit_id, $date]);
}

// Appointments

function create_calendar_appointment(
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
  $urgent = false,
  $travel_before = 0,
  $travel_after = 0,
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
    `urgent`,
    `travel_before`,
    `travel_after`
  ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $calendar_id,
    $title,
    $content,
    $starts_at,
    $ends_at,
    $location,
    $meeting,
    $recurrence,
    $all_day,
    $going,
    $urgent,
    $travel_before,
    $travel_after
  ]) ? $id : null;
}

function create_subscription_appointment(
  $id,
  $subscription_id,
  $title,
  $content,
  $starts_at,
  $ends_at,
  $location,
  $meeting,
  $all_day,
  $recurrence,
  $recurrence_until,
  $recurrence_count
) {
  return exec_query('INSERT INTO `appointments` (
    `id`,
    `subscription_id`,
    `title`,
    `content`,
    `starts_at`,
    `ends_at`,
    `location`,
    `meeting`,
    `all_day`,
    `recurrence`,
    `recurrence_until`,
    `recurrence_count`
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    $id,
    $subscription_id,
    $title,
    $content,
    $starts_at,
    $ends_at,
    $location,
    $meeting,
    $all_day,
    $recurrence,
    $recurrence_until,
    $recurrence_count
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
  $urgent = false,
  $travel_before = 0,
  $travel_after = 0,
  $calendar_id = null,
) {
  // calendar_id is COALESCEd so callers that don't touch it (passing null)
  // leave the appointment on its current calendar.
  return exec_query('UPDATE `appointments` SET
    `calendar_id` = COALESCE(?, `calendar_id`),
    `title` = ?,
    `content` = ?,
    `starts_at` = ?,
    `ends_at` = ?,
    `location` = ?,
    `meeting` = ?,
    `recurrence` = ?,
    `all_day` = ?,
    `going` = ?,
    `urgent` = ?,
    `travel_before` = ?,
    `travel_after` = ?
  WHERE id = ?', [
    $calendar_id,
    $title,
    $content,
    $starts_at,
    $ends_at,
    $location,
    $meeting,
    $recurrence,
    $all_day,
    $going,
    $urgent,
    $travel_before,
    $travel_after,
    $id
  ]);
}

function update_appointment_times($id, $starts_at, $ends_at) {
  return exec_query('UPDATE `appointments` SET
    `starts_at` = ?,
    `ends_at` = ?
  WHERE id = ?', [$starts_at, $ends_at, $id]);
}

function update_appointment_meta($id, $going, $urgent, $travel_before = 0, $travel_after = 0) {
  return exec_query('UPDATE `appointments` SET
    `going` = ?,
    `urgent` = ?,
    `travel_before` = ?,
    `travel_after` = ?
  WHERE id = ?', [$going, $urgent, $travel_before, $travel_after, $id]);
}

function update_appointment_body(
  $id,
  $title,
  $content,
  $starts_at,
  $ends_at,
  $location,
  $meeting,
  $all_day,
  $recurrence,
  $recurrence_until,
  $recurrence_count
) {
  return exec_query('UPDATE `appointments` SET
    `title` = ?,
    `content` = ?,
    `starts_at` = ?,
    `ends_at` = ?,
    `location` = ?,
    `meeting` = ?,
    `all_day` = ?,
    `recurrence` = ?,
    `recurrence_until` = ?,
    `recurrence_count` = ?
  WHERE id = ?', [
    $title,
    $content,
    $starts_at,
    $ends_at,
    $location,
    $meeting,
    $all_day,
    $recurrence,
    $recurrence_until,
    $recurrence_count,
    $id
  ]);
}

// Stops a recurring appointment at the given moment without deleting it,
// preserving its past occurrences.
function end_appointment_recurrence($id, $moment) {
  return exec_query('UPDATE `appointments` SET
    `recurrence_until` = ?,
    `recurrence_count` = NULL
  WHERE id = ?', [$moment, $id]);
}

// Non-recurring appointments overlapping [$from, $to). Recurring ones are
// fetched separately and expanded per occurrence by the caller, so excluding
// them here avoids rendering the master twice.
function list_appointments($from, $to) {
  return all('SELECT
    a.*,
    c.title AS calendar_title,
    c.subtitle AS calendar_subtitle,
    c.color AS calendar_color,
    s.title AS subscription_title,
    s.subtitle AS subscription_subtitle,
    s.color AS subscription_color,
    s.`filter` AS subscription_filter
  FROM `appointments` a
  LEFT JOIN `calendars` c ON c.id = a.calendar_id
  LEFT JOIN `subscriptions` s ON s.id = a.subscription_id
  WHERE a.recurrence IS NULL AND a.starts_at < ? AND a.ends_at > ?
  ORDER BY a.starts_at', [$to, $from]);
}

function list_appointments_by_subscription($subscription_id) {
  return all('SELECT * FROM `appointments`
    WHERE `subscription_id` = ?', [$subscription_id]);
}

function list_recurring_appointments($from, $to) {
  return all('SELECT
    a.*,
    c.title AS calendar_title,
    c.subtitle AS calendar_subtitle,
    c.color AS calendar_color,
    s.title AS subscription_title,
    s.subtitle AS subscription_subtitle,
    s.color AS subscription_color,
    s.`filter` AS subscription_filter
  FROM `appointments` a
  LEFT JOIN `calendars` c ON c.id = a.calendar_id
  LEFT JOIN `subscriptions` s ON s.id = a.subscription_id
  WHERE a.recurrence IS NOT NULL
    AND a.starts_at < ?
    AND (a.recurrence_until IS NULL OR a.recurrence_until >= ?)
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
    s.color AS subscription_color,
    s.`filter` AS subscription_filter
  FROM `appointments` a
  LEFT JOIN `calendars` c ON c.id = a.calendar_id
  LEFT JOIN `subscriptions` s ON s.id = a.subscription_id
  WHERE a.id = ?', [$id]);
}

function delete_appointment($id) {
  return exec_query('DELETE FROM `appointments` WHERE `id` = ?', [$id]);
}

define('ENUM_SSL_MODE', ['plain', 'tls', 'ssl']);

// Contacts

define('ENUM_SOCIAL_TYPE', ['instagram', 'discord', 'snapchat', 'github', 'codeberg', 'linkedin', 'matrix', 'pinterest', 'twitter', 'youtube', 'facebook', 'activitypub', 'bsky']);

function list_contacts() {
  return all("SELECT contacts.*,
    (SELECT GROUP_CONCAT(tags.label, ' ')
      FROM tags
      JOIN contacts_tags ON contacts_tags.tag_id = tags.id
      WHERE contacts_tags.contact_id = contacts.id
    ) AS tag_labels,
    (SELECT GROUP_CONCAT(contact_emails.email, ' ')
      FROM contact_emails
      WHERE contact_emails.contact_id = contacts.id
    ) AS emails,
    (SELECT GROUP_CONCAT(contact_phone_numbers.phone_number, ' ')
      FROM contact_phone_numbers
      WHERE contact_phone_numbers.contact_id = contacts.id
    ) AS phone_numbers,
    (SELECT GROUP_CONCAT(contact_roles.name, ' ')
      FROM contact_roles
      WHERE contact_roles.contact_id = contacts.id
    ) AS org_names FROM contacts") ?? [];
}

function get_contact($id) {
  $contact = one('SELECT * FROM `contacts` WHERE id = ?', [$id]);

  if(!$contact) return $contact;

  $contact['emails'] = list_contact_emails($id);
  $contact['phone_numbers'] = list_contact_phone_numbers($id);
  $contact['urls'] = list_contact_urls($id);
  $contact['socials'] = list_contact_socials($id);
  $contact['roles'] = list_contact_roles($id);
  $contact['addresses'] = list_contact_addresses($id);
  $contact['tags'] = list_contact_tags($id);

  return $contact;
}

function list_contact_emails($id) {
  return all('SELECT * FROM `contact_emails` WHERE contact_id = ?', [$id]);
}

function list_contact_phone_numbers($id) {
  return all('SELECT * FROM `contact_phone_numbers` WHERE contact_id = ?', [$id]);
}

function list_contact_urls($id) {
  return all('SELECT * FROM `contact_urls` WHERE contact_id = ?', [$id]);
}

function list_contact_socials($id) {
  return all('SELECT * FROM `contact_socials` WHERE contact_id = ?', [$id]);
}

function list_contact_roles($id) {
  return all('SELECT * FROM `contact_roles` WHERE contact_id = ?', [$id]);
}

function list_contact_addresses($id) {
  return all('SELECT a.*, ca.label AS link_label FROM `contact_addresses` ca
    JOIN `addresses` a ON a.id = ca.address_id WHERE ca.contact_id = ?', [$id]);
}

function list_contact_tags($id) {
  return all('SELECT t.* FROM `tags` t
    JOIN `contacts_tags` ct ON ct.tag_id = t.id WHERE ct.contact_id = ?', [$id]);
}

function create_contact(
  $display_name,
  $first_name,
  $middle_name,
  $infix,
  $last_name,
  $birth_day,
  $birth_month,
  $birth_year,
  $note
) {
  $birthday = validate_birthday($birth_day, $birth_month, $birth_year);

  if($birthday === null) return false;

  [$birth_day, $birth_month, $birth_year] = $birthday;

  $ok = exec_query('INSERT INTO `contacts`
    (`display_name`, `first_name`, `middle_name`, `infix`, `last_name`, `birth_day`, `birth_month`, `birth_year`, `note`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$display_name, $first_name, $middle_name, $infix, $last_name, $birth_day, $birth_month, $birth_year, $note]);

  return $ok ? DBH->lastInsertId() : null;
}

function update_contact(
  $id,
  $display_name,
  $first_name,
  $middle_name,
  $infix,
  $last_name,
  $birth_day,
  $birth_month,
  $birth_year,
  $note
) {
  $birthday = validate_birthday($birth_day, $birth_month, $birth_year);

  if($birthday === null) return false;

  [$birth_day, $birth_month, $birth_year] = $birthday;

  return exec_query('UPDATE `contacts` SET
    `display_name` = ?, `first_name` = ?, `middle_name` = ?,
    `infix` = ?, `last_name` = ?, `birth_day` = ?, `birth_month` = ?, `birth_year` = ?, `note` = ? WHERE id = ?',
    [$display_name, $first_name, $middle_name, $infix, $last_name, $birth_day, $birth_month, $birth_year, $note, $id]);
}

function update_contact_note($id, $note) {
  return exec_query('UPDATE `contacts` SET `note` = ? WHERE id = ?', [$note, $id]);
}

function set_contact_emails($id, $rows) {
  set_children('contact_emails', 'contact_id', $id, $rows);
}

function set_contact_phone_numbers($id, $rows) {
  set_children('contact_phone_numbers', 'contact_id', $id, $rows);
}

function set_contact_urls($id, $rows) {
  set_children('contact_urls', 'contact_id', $id, $rows);
}

function set_contact_socials($id, $rows) {
  set_children('contact_socials', 'contact_id', $id, $rows);
}

function set_contact_roles($id, $rows) {
  set_children('contact_roles', 'contact_id', $id, $rows);
}

function set_contact_addresses($id, $rows) {
  exec_query('DELETE FROM `contact_addresses` WHERE contact_id = ?', [$id]);

  foreach($rows as $row) {
    $address_id = create_address(
      label: null,
      street_name: $row['street_name'],
      street_number: $row['street_number'],
      postal_code: $row['postal_code'],
      city: $row['city'],
      province: $row['province'],
      country: $row['country'],
      timezone: $row['timezone']
    );

    exec_query('INSERT INTO `contact_addresses` (contact_id, label, address_id) VALUES (?, ?, ?)',
      [$id, $row['label'], $address_id]);
  }
}

function delete_contact($id) {
  return exec_query('DELETE FROM `contacts` WHERE id = ?', [$id]);
}

function validate_birthday($day, $month, $year) {
  if(($day === null) !== ($month === null)) return null;
  if($year !== null && $day === null) return null;
  if($day !== null && !checkdate($month, $day, $year ?: 2000)) return null;

  return [$day, $month, $year];
}

function list_organisations() {
  return all("SELECT organisations.*,
    (SELECT GROUP_CONCAT(tags.label, ' ')
      FROM tags
      JOIN orgs_tags ON orgs_tags.tag_id = tags.id
      WHERE orgs_tags.org_id = organisations.id
    ) AS tag_labels,
    (SELECT GROUP_CONCAT(org_emails.email, ' ')
      FROM org_emails
      WHERE org_emails.org_id = organisations.id
    ) AS emails,
    (SELECT GROUP_CONCAT(org_phone_numbers.phone_number, ' ')
      FROM org_phone_numbers
      WHERE org_phone_numbers.org_id = organisations.id
    ) AS phone_numbers FROM organisations") ?? [];
}

function get_organisation($id) {
  $organisation = one('SELECT * FROM `organisations` WHERE id = ?', [$id]);

  if(!$organisation) return $organisation;

  $organisation['emails'] = list_organisation_emails($id);
  $organisation['phone_numbers'] = list_organisation_phone_numbers($id);
  $organisation['urls'] = list_organisation_urls($id);
  $organisation['socials'] = list_organisation_socials($id);
  $organisation['addresses'] = list_organisation_addresses($id);
  $organisation['tags'] = list_organisation_tags($id);

  return $organisation;
}

function list_organisation_emails($id) {
  return all('SELECT * FROM `org_emails` WHERE org_id = ?', [$id]);
}

function list_organisation_phone_numbers($id) {
  return all('SELECT * FROM `org_phone_numbers` WHERE org_id = ?', [$id]);
}

function list_organisation_urls($id) {
  return all('SELECT * FROM `org_urls` WHERE org_id = ?', [$id]);
}

function list_organisation_socials($id) {
  return all('SELECT * FROM `org_socials` WHERE org_id = ?', [$id]);
}

function list_organisation_addresses($id) {
  return all('SELECT a.*, oa.label AS link_label FROM `org_addresses` oa
    JOIN `addresses` a ON a.id = oa.address_id WHERE oa.org_id = ?', [$id]);
}

function list_organisation_tags($id) {
  return all('SELECT t.* FROM `tags` t
    JOIN `orgs_tags` ot ON ot.tag_id = t.id WHERE ot.org_id = ?', [$id]);
}

function create_organisation($display_name, $legal_name, $registration_number, $vat_number, $note) {
  $ok = exec_query('INSERT INTO `organisations`
    (`display_name`, `legal_name`, `registration_number`, `vat_number`, `note`)
    VALUES (?, ?, ?, ?, ?)',
    [$display_name, $legal_name, $registration_number, $vat_number, $note]);

  return $ok ? DBH->lastInsertId() : null;
}

function update_organisation($id, $display_name, $legal_name, $registration_number, $vat_number, $note) {
  return exec_query('UPDATE `organisations` SET
    `display_name` = ?,
    `legal_name` = ?,
    `registration_number` = ?,
    `vat_number` = ?,
    `note` = ? WHERE id = ?',
    [$display_name, $legal_name, $registration_number, $vat_number, $note, $id]);
}

function update_organisation_note($id, $note) {
  return exec_query('UPDATE `organisations` SET `note` = ? WHERE id = ?', [$note, $id]);
}

function set_organisation_emails($id, $rows) {
  set_children('org_emails', 'org_id', $id, $rows);
}

function set_organisation_phone_numbers($id, $rows) {
  set_children('org_phone_numbers', 'org_id', $id, $rows);
}

function set_organisation_urls($id, $rows) {
  set_children('org_urls', 'org_id', $id, $rows);
}

function set_organisation_socials($id, $rows) {
  set_children('org_socials', 'org_id', $id, $rows);
}

function set_organisation_addresses($id, $rows) {
  exec_query('DELETE FROM `org_addresses` WHERE org_id = ?', [$id]);

  foreach($rows as $row) {
    $address_id = create_address(
      label: null,
      street_name: $row['street_name'],
      street_number: $row['street_number'],
      postal_code: $row['postal_code'],
      city: $row['city'],
      province: $row['province'],
      country: $row['country'],
      timezone: $row['timezone']
    );

    exec_query('INSERT INTO `org_addresses` (org_id, label, address_id) VALUES (?, ?, ?)',
      [$id, $row['label'], $address_id]);
  }
}

function delete_organisation($id) {
  return exec_query('DELETE FROM `organisations` WHERE id = ?', [$id]);
}

function set_children($table, $fk, $id, $rows) {
  exec_query("DELETE FROM `$table` WHERE `$fk` = ?", [$id]);

  foreach($rows as $row) {
    $cols = array_keys($row);
    $names = implode(", ", array_map(fn($c) => "`$c`", [$fk, ...$cols]));
    $marks = implode(", ", array_fill(0, count($cols) + 1, "?"));
    exec_query("INSERT INTO `$table` ($names) VALUES ($marks)", [$id, ...array_values($row)]);
  }
}

// Addresses

function list_addresses() {
  return all("SELECT * FROM `addresses`
    ORDER BY CASE WHEN `label` IS NULL OR `label` = '' THEN 1 ELSE 0 END, `city`, `street_name`");
}

function get_address($id) {
  return one("SELECT * FROM `addresses` WHERE id = ?", [$id]);
}

function create_address(
  $label,
  $street_name,
  $street_number,
  $postal_code,
  $city,
  $province,
  $country,
  $timezone
) {
  // If an address already exists verbatim, we reuse the existing address row.
  // This keeps the database free of duplicates.

  $existing = one('SELECT id FROM `addresses`
    WHERE street_name = ? AND street_number = ? AND postal_code = ? AND city = ? AND country = ?',
    [$street_name, $street_number, $postal_code, $city, $country]);

  if($existing) return $existing['id'];

  exec_query('INSERT INTO `addresses` (
    `label`,
    `street_name`,
    `street_number`,
    `postal_code`,
    `city`,
    `province`,
    `country`,
    `timezone`
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
    $label,
    $street_name,
    $street_number,
    $postal_code,
    $city,
    $province,
    $country,
    $timezone
  ]);

  return DBH->lastInsertId();
}

function update_address(
  $id,
  $label,
  $street_name,
  $street_number,
  $postal_code,
  $city,
  $province,
  $country,
  $timezone
) {
  return exec_query('UPDATE `addresses` SET
    `label` = ?,
    `street_name` = ?,
    `street_number` = ?,
    `postal_code` = ?,
    `city` = ?,
    `province` = ?,
    `country` = ?,
    `timezone` = ?
  WHERE id = ?', [
    $label,
    $street_name,
    $street_number,
    $postal_code,
    $city,
    $province,
    $country,
    $timezone,
    $id
  ]);
}

function delete_address($id) {
  return exec_query('DELETE FROM `addresses` WHERE id = ?', [$id]);
}

// Configuration

function config() {
  $map = [];
  $rows = all("SELECT * FROM `config`");

  foreach($rows as $row)
    $map[$row['property']] = $row['value'];

  return $map;
}

function update_config($property, $value) {
  // We delete first, because otherwise we need to differentiate on adapter to
  // use different syntax (ON CONFLICT, ON DUPLICATE KEY etc.) and that is a headache.
  // We also don't care if this first query succeeds (bc yk it might not exist).

  exec_query('DELETE FROM `config` WHERE `property` = ?', [$property]);

  if($value === null) return true;

  return exec_query('INSERT INTO `config` (`property`, `value`) VALUES (?, ?)', [$property, $value]);
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
  but store is already at v$current_store_version");
}

if($current_store_version < $latest_store_version) {
  migrate(from: $current_store_version, to: $latest_store_version);
}

if(INITIAL_RUN) seed();
