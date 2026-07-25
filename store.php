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

define('ENUM_SSL_MODE', ['plain', 'tls', 'ssl']);
define('ENUM_TASK_STATUS', ['todo', 'wip', 'backlog', 'blocked', 'done', 'nvm']);
define('ENUM_WISH_STATUS', ['dream', 'bought', 'nvm']);

// Notes

function put_note($title, $content, $date = null) {
  $ok = exec_query('INSERT INTO notes (
    id,
    title,
    content,
    written_at
  ) VALUES (?, ?, ?, ?)', [
    $id = generate_humid(),
    $title,
    $content,
    $date ?? gmdate('c')
  ]);

  return $ok ? $id : $ok;
}

function update_note($id, $title, $content, $date = null) {
  return exec_query('UPDATE notes SET
    title = ?,
    content = ?,
    written_at = ?
  WHERE id = ?', [$title, $content, $date, $id]);
}

function get_note($id) {
  return one('SELECT * FROM notes WHERE id = ?', [$id]);
}

function get_note_tags($id) {
  return tags_of('notes_tags', 'note_id', $id);
}

function set_note_tags($id, $tag_ids) {
  set_tags('notes_tags', 'note_id', $id, $tag_ids);
}

function list_notes($query = "") {
  [$tags, $terms] = \query\parse($query);

  $where = [];
  $params = [];

  foreach($terms as $term) {
    $where[] = '(LOWER(title) LIKE ? OR LOWER(content) LIKE ?)';
    $like = "%" . mb_strtolower($term) . "%";
    $params[] = $like;
    $params[] = $like;
  }

  foreach($tags as $id) {
    $where[] = 'EXISTS (SELECT 1 FROM notes_tags nt
      WHERE nt.note_id = notes.id AND nt.tag_id = ?)';
    $params[] = $id;
  }

  $sql = 'SELECT * FROM notes';
  if($where) $sql .= ' WHERE ' . join(' AND ', $where);
  $sql .= ' ORDER BY CASE WHEN written_at IS NULL THEN 1 ELSE 0 END, written_at DESC';

  return all($sql, $params) ?? [];
}

function delete_note($id) {
  return exec_query('DELETE FROM notes WHERE id = ?', [$id]);
}

// Tasks

function put_task(
  $title,
  $content,
  $status,
  $urgent = false,
  $recurrence = null,
  $open_at = null,
  $due_at = null,
  $due_all_day = false,
  $expire_at = null,
  $comment = null
) {
  in_array($status, ENUM_TASK_STATUS) or die("status $status does not exist");

  $open_at ??= gmdate('c');

  $ok = exec_query('INSERT INTO tasks (
    id,
    title,
    content,
    urgent,
    recurrence,
    open_at,
    due_at,
    due_all_day,
    expire_at
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $title,
    $content,
    $urgent,
    $recurrence,
    $open_at,
    $due_at,
    $due_all_day,
    $expire_at
  ]);

  if(!$ok) return $ok;

  $ok = exec_query('INSERT INTO task_log (
    task_id,
    changed_at,
    status,
    comment
  ) VALUES (?, ?, ?, ?)', [
    $id,
    gmdate('c'),
    $status,
    $comment
  ]);

  return $ok ? $id : $ok;
}

function update_task(
  $id,
  $title,
  $content,
  $urgent,
  $recurrence,
  $open_at,
  $due_at,
  $due_all_day,
  $expire_at
) {
  return exec_query('UPDATE tasks SET
    title = ?,
    content = ?,
    urgent = ?,
    recurrence = ?,
    open_at = ?,
    due_at = ?,
    due_all_day = ?,
    expire_at = ?
  WHERE id = ?', [
    $title,
    $content,
    $urgent,
    $recurrence,
    $open_at,
    $due_at,
    $due_all_day,
    $expire_at,
    $id
  ]);
}

function set_task_urgent($id, $urgent) {
  return exec_query('UPDATE tasks SET urgent = ? WHERE id = ?', [$urgent, $id]);
}

function set_task_status($id, $status, $comment = "") {
  in_array($status, ENUM_TASK_STATUS) or die("status $status does not exist");

  // Skip redundant transitions: if the latest entry already has this status and
  // no comment is being added, there is nothing new to record. This stops rapid
  // toggles from the listing (which race the async re-render) from stacking up
  // empty log events.
  $latest = one('SELECT status FROM task_log
    WHERE task_id = ? ORDER BY changed_at DESC, id DESC', [$id]);
  if($latest && $latest['status'] === $status && !$comment) return true;

  return exec_query('INSERT INTO task_log (
    task_id,
    changed_at,
    status,
    comment
  ) VALUES (?, ?, ?, ?)', [
    $id,
    gmdate('c'),
    $status,
    $comment
  ]);
}

function get_task_tags($id) {
  return tags_of('tasks_tags', 'task_id', $id);
}

function set_task_tags($id, $tag_ids) {
  set_tags('tasks_tags', 'task_id', $id, $tag_ids);
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
  $expired = null;

  foreach(explode(" ", $query) as $segment) {
    $parts = explode(":", $segment);
    if(count($parts) != 2) continue;
    [$selector, $value] = $parts;

    if($value == 'urgent') $urgent = $selector == "is";
    elseif($value == 'expired') $expired = $selector == 'is';
    elseif($selector == "is" && $value == 'open') $include = [...$include, 'todo', 'wip', 'blocked'];
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
      log.changed_at as updated_date,
      (SELECT done.changed_at FROM task_log done
        WHERE done.task_id = tasks.id AND done.status = 'done'
        ORDER BY done.changed_at DESC, done.id DESC LIMIT 1) AS last_done
    FROM tasks
    LEFT JOIN task_log log
      ON log.id = (
        SELECT ranked.id
        FROM task_log ranked
        WHERE ranked.task_id = tasks.id
        ORDER BY ranked.changed_at DESC, ranked.id DESC
        LIMIT 1
      )
    LEFT JOIN tasks_tags tt ON tt.task_id = tasks.id
    LEFT JOIN tags ON tags.id = tt.tag_id
    ORDER BY
      due_at NULLS LAST,
      expire_at NULLS LAST,
      open_at");

  if(!$rows) return $rows;

  $override = $override ?? [];
  $result = [];

  $colors = array_column(list_tags(), 'color', 'id');

  foreach(collect_by($rows, 'tag', 'tags') as $task) {
    foreach($task['tags'] as &$tag) {
      $tag['color'] = @$colors[$tag['id']] ?: $tag['color'];
    }
    unset($tag);

    $state = \recurrence\task_state($task);
    $task = array_merge($task, $state);

    if(in_array($task['id'], $override, true)) { $result[] = $task; continue; }

    $is_expired = $task['expire_at'] && strtotime($task['expire_at']) <= time();
    if($expired !== null && $is_expired != $expired) continue;
    if($respect_horizon && !$is_expired && !$state['visible']) continue;
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
    task.open_at,
    task.due_at,
    task.due_all_day,
    task.expire_at,
    log.status,
    log.comment,
    log.changed_at as updated_date,
    (SELECT done.changed_at FROM task_log done
      WHERE done.task_id = task.id AND done.status = \'done\'
      ORDER BY done.changed_at DESC, done.id DESC LIMIT 1) AS last_done
  FROM tasks task
  LEFT JOIN task_log log ON log.task_id = task.id
  WHERE task.id = ?
  ORDER BY log.changed_at DESC, log.id DESC', [$id]);

  if($task === false) return $task;

  $task = array_merge($task, \recurrence\task_state($task));

  $tags = all('SELECT tags.label FROM tags
    JOIN tasks_tags tt ON tt.tag_id = tags.id
    WHERE tt.task_id = ?', [$id]);

  if($tags === false) return $tags;

  $task['tags'] = array_column($tags, 'label');

  return $task;
}

function get_task_log($id) {
  return all('SELECT * FROM task_log WHERE task_id = ? ORDER BY changed_at ASC, id ASC', [$id]) ?? [];
}

function amend_task_status($id, $comment) {
  $log = all('SELECT * FROM task_log
    WHERE task_id = ? ORDER BY changed_at DESC, id DESC LIMIT 2', [$id]);

  if(!$log) return $log;

  return exec_query('UPDATE task_log SET comment = ?
    WHERE id = ? AND task_id = ?', [$comment, $log[0]['id'], $id]);
}

function undo_task_status($id, $log_id) {
  $log = all('SELECT id, status FROM task_log
    WHERE task_id = ? ORDER BY changed_at DESC, id DESC LIMIT 2', [$id]);

  if(!$log || count($log) < 2 || $log[0]['id'] != $log_id ||
    $log[0]['status'] == $log[1]['status']) return false;

  $query = exec_query('DELETE FROM task_log
    WHERE id = ? AND task_id = ? AND id = (
      SELECT latest.id FROM (
        SELECT id FROM task_log
        WHERE task_id = ? ORDER BY changed_at DESC, id DESC LIMIT 1
      ) latest
    )', [$log_id, $id, $id]);

  return $query && $query->rowCount() == 1;
}

function delete_task($id) {
  return exec_query('DELETE FROM tasks WHERE id = ?', [$id]);
}

// Wishes

function put_wish($title, $content, $status, $urgent = false, $date = null) {
  in_array($status, ENUM_WISH_STATUS) or die("status $status does not exist");

  $ok = exec_query('INSERT INTO wishes (
    id,
    title,
    content,
    urgent,
    added_at
  ) VALUES (?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $title,
    $content,
    $urgent,
    $date ?? gmdate('c')
  ]);

  if(!$ok) return $ok;

  $ok = exec_query('INSERT INTO wish_log (
    wish_id,
    status
  ) VALUES (?, ?)', [$id, $status]);

  return $ok ? $id : $ok;
}

function set_wish_status($id, $status, $comment = "") {
  in_array($status, ENUM_WISH_STATUS) or die("status $status does not exist");

  // Skip redundant transitions (see set_task_status): the wish listing has the
  // same toggle, so rapid clicks would otherwise stack up empty log events.
  $latest = one('SELECT status FROM wish_log
    WHERE wish_id = ? ORDER BY changed_at DESC, id DESC', [$id]);
  if($latest && $latest['status'] === $status && !$comment) return true;

  return exec_query('INSERT INTO wish_log (
    wish_id,
    status,
    comment
  ) VALUES (?, ?, ?)', [$id, $status, $comment]);
}

function update_wish($id, $title, $content, $urgent, $date = null) {
  return exec_query('UPDATE wishes SET
    title = ?,
    content = ?,
    urgent = ?,
    added_at = ?
  WHERE id = ?', [$title, $content, $urgent, $date, $id]);
}

function get_wish($id) {
  $wish = one('SELECT
    wishes.*,
    log.status,
    log.comment,
    log.changed_at as updated_date
  FROM wishes
  LEFT JOIN wish_log log
    ON log.id = (
      SELECT ranked.id
      FROM wish_log ranked
      WHERE ranked.wish_id = wishes.id
      ORDER BY ranked.changed_at DESC, ranked.id DESC
      LIMIT 1
    )
  WHERE wishes.id = ?', [$id]);

  if(!$wish) return $wish;

  $wish['urls'] = list_wish_urls($id);

  return $wish;
}

function list_wish_urls($id) {
  return all('SELECT * FROM wish_urls WHERE wish_id = ?', [$id]);
}

function set_wish_urls($id, $rows) {
  set_children('wish_urls', 'wish_id', $id, $rows);
}

function get_wish_tags($id) {
  return tags_of('wishes_tags', 'wish_id', $id);
}

function set_wish_tags($id, $tag_ids) {
  set_tags('wishes_tags', 'wish_id', $id, $tag_ids);
}

function get_wish_log($id) {
  return all('SELECT * FROM wish_log WHERE wish_id = ? ORDER BY changed_at ASC', [$id]) ?? [];
}

function list_wishes($statuses = [], $override = []) {
  $rows = all("SELECT
      wishes.*,
      log.status,
      log.comment,
      log.changed_at as updated_date,
      (SELECT SUM(price) FROM wish_urls WHERE wish_urls.wish_id = wishes.id) as total_price
    FROM wishes
    LEFT JOIN wish_log log
      ON log.id = (
        SELECT ranked.id
        FROM wish_log ranked
        WHERE ranked.wish_id = wishes.id
        ORDER BY ranked.changed_at DESC, ranked.id DESC
        LIMIT 1
      )
    ORDER BY wishes.added_at DESC, wishes.id DESC");

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
  return exec_query('DELETE FROM wishes WHERE id = ?', [$id]);
}

// Bookmarks

function put_bookmark($url, $label = null, $note = null, $favicon = null, $date = null) {
  $ok = exec_query('INSERT INTO bookmarks (
    id,
    label,
    url,
    note,
    favicon,
    saved_at
  ) VALUES (?, ?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $label,
    $url,
    $note,
    $favicon,
    $date ?? gmdate('c')
  ]);

  return $ok ? $id : $ok;
}

function update_bookmark($id, $label, $url, $note = null, $date = null) {
  return exec_query('UPDATE bookmarks SET
    label = ?,
    url = ?,
    note = ?,
    saved_at = ?
  WHERE id = ?', [$label, $url, $note, $date, $id]);
}

function update_bookmark_favicon($id, $favicon) {
  return exec_query('UPDATE bookmarks SET favicon = ? WHERE id = ?', [$favicon, $id]);
}

function get_bookmark_tags($id) {
  return tags_of('bookmarks_tags', 'bookmark_id', $id);
}

function set_bookmark_tags($id, $tag_ids) {
  set_tags('bookmarks_tags', 'bookmark_id', $id, $tag_ids);
}

function get_bookmark($id) {
  return one('SELECT * FROM bookmarks WHERE id = ?', [$id]);
}

function list_bookmarks($query = "") {
  [$tags, $terms] = \query\parse($query);

  $where = [];
  $params = [];

  foreach($terms as $term) {
    $where[] = '(LOWER(label) LIKE ? OR LOWER(url) LIKE ? OR LOWER(note) LIKE ?)';
    $like = "%" . mb_strtolower($term) . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
  }

  foreach($tags as $id) {
    $where[] = 'EXISTS (SELECT 1 FROM bookmarks_tags bt
      WHERE bt.bookmark_id = bookmarks.id AND bt.tag_id = ?)';
    $params[] = $id;
  }

  $sql = 'SELECT * FROM bookmarks';
  if($where) $sql .= ' WHERE ' . join(' AND ', $where);
  $sql .= ' ORDER BY CASE WHEN saved_at IS NULL THEN 1 ELSE 0 END, saved_at DESC';

  return all($sql, $params) ?? [];
}

function delete_bookmark($id) {
  return exec_query('DELETE FROM bookmarks WHERE id = ?', [$id]);
}

// Tracker

function put_timing($description, $starts_at, $ends_at, $task_id = null) {
  if($task_id) get_task($task_id) or die("task with ID $task_id does not exist");

  $ok = exec_query('INSERT INTO timings (
    id,
    description,
    starts_at,
    ends_at,
    task_id
  ) VALUES (?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $description,
    $starts_at,
    $ends_at,
    $task_id
  ]);

  return $ok ? $id : $ok;
}

function update_timing($id, $description, $starts_at, $ends_at, $task_id) {
  if($task_id) get_task($task_id) or die("task with ID $task_id does not exist");

  return exec_query('UPDATE timings SET
    description = ?,
    starts_at = ?,
    ends_at = ?,
    task_id = ?
  WHERE id = ?', [
    $description,
    $starts_at,
    $ends_at,
    $task_id,
    $id
  ]);
}

function get_timing_tags($id) {
  return tags_of('timings_tags', 'timing_id', $id);
}

function set_timing_tags($id, $tag_ids) {
  set_tags('timings_tags', 'timing_id', $id, $tag_ids);
}

function list_timings() {
  return all('SELECT * FROM timings ORDER BY starts_at DESC');
}

// Timings overlapping [$from, $to), each carrying its first tag (lowest id).
// The caller resolves that tag to its root to pick a colour; keeping it a bare
// id keeps this query portable across the SQL engines we target.
function list_timings_between($from, $to) {
  return all('SELECT
    t.*,
    (SELECT tt.tag_id FROM timings_tags tt
      WHERE tt.timing_id = t.id
      ORDER BY tt.tag_id ASC LIMIT 1) AS first_tag_id
  FROM timings t
  WHERE t.starts_at < ? AND t.ends_at > ?
  ORDER BY t.starts_at', [$to, $from]);
}

function get_timing($id) {
  return one('SELECT * FROM timings WHERE id = ?', [$id]);
}

function delete_timing($id) {
  return exec_query('DELETE FROM timings WHERE id = ?', [$id]);
}

function list_timing_tags($from, $to) {
  return all('SELECT t.id, t.starts_at, t.ends_at, tt.tag_id
    FROM timings t
    JOIN timings_tags tt ON tt.timing_id = t.id
    WHERE t.starts_at < ? AND t.ends_at > ?', [$to, $from]) ?? [];
}

// Quotas

function list_quotas() {
  $quotas = all('SELECT q.*, t.label, t.color
    FROM quotas q
    JOIN tags t ON t.id = q.tag_id
    ORDER BY t.position ASC, t.id DESC') ?? [];

  return inherit_tag_colors($quotas, id_key: 'tag_id');
}

function quota_minutes($tag_id, $period, $hours, $minutes, $start_date) {
  if(!$tag_id || !get_tag($tag_id)) return null;
  if(!in_array($period, ['week', 'month'])) return null;
  if($hours === null || $minutes === null || $hours < 0 || $minutes < 0 || $minutes > 59) return null;
  if(!$start_date || \cast_date($start_date) != $start_date) return null;

  $duration = $hours * 60 + $minutes;
  return $duration > 0 ? $duration : null;
}

function put_quota($tag_id, $period, $hours, $minutes, $start_date) {
  $duration = quota_minutes($tag_id, $period, $hours, $minutes, $start_date);
  if(!$duration) return false;

  return exec_query('INSERT INTO quotas (tag_id, period, minutes, start_date)
    VALUES (?, ?, ?, ?)', [$tag_id, $period, $duration, $start_date]);
}

function update_quota($tag_id, $period, $hours, $minutes, $start_date) {
  $duration = quota_minutes($tag_id, $period, $hours, $minutes, $start_date);
  if(!$duration) return false;

  return exec_query('UPDATE quotas
    SET period = ?, minutes = ?, start_date = ? WHERE tag_id = ?',
    [$period, $duration, $start_date, $tag_id]);
}

function delete_quota($tag_id) {
  return exec_query('DELETE FROM quotas WHERE tag_id = ?', [$tag_id]);
}

// Tags

function put_tag($label, $color, $parent_id) {
  if($parent_id) get_tag($parent_id) or die("tag with ID $parent_id does not exist");

  $ok = exec_query('INSERT INTO tags (
    label,
    color,
    parent_id,
    position
  ) VALUES (?, ?, ?, ?)', [
    $label,
    $color,
    $parent_id,
    prepend_order('tags')
  ]);

  return $ok ? DBH->lastInsertId() : null;
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

  return exec_query('UPDATE tags SET
    label = ?,
    color = COALESCE(?, color),
    parent_id = ?
  WHERE id = ?', [
    $label,
    $color,
    $parent_id,
    $id
  ]);
}

function list_tags() {
  $tags = all('SELECT * FROM tags ORDER BY position ASC, id DESC');

  $children = [];
  foreach($tags as $tag) $children[$tag['parent_id']][] = $tag;

  $result = [];
  $walk = function($parent_id, $color = null) use (&$walk, &$children, &$result) {
    foreach($children[$parent_id] ?? [] as $tag) {
      if($parent_id) $tag['color'] = $color;
      $result[] = $tag;
      $walk($tag['id'], $tag['color']);
    }
  };

  $walk(null);
  return $result;
}

function inherit_tag_colors($items, $id_key = 'id') {
  $colors = array_column(list_tags(), 'color', 'id');

  return array_map(function($item) use ($colors, $id_key) {
    $item['color'] = @$colors[$item[$id_key]] ?: $item['color'];
    return $item;
  }, $items);
}

function reorder_tags($ids) {
  foreach(array_values($ids) as $order => $id) {
    $ok = exec_query('UPDATE tags
      SET position = ? WHERE id = ?', [$order, $id]);

    if(!$ok) return false;
  }

  return true;
}

function tags_of($table, $fk, $id) {
  $tags = all("SELECT tags.* FROM tags
    JOIN $table link ON link.tag_id = tags.id
    WHERE link.$fk = ?
    ORDER BY tags.position ASC, tags.id DESC", [$id]);

  return $tags === false ? false : inherit_tag_colors($tags);
}

function set_tags($table, $fk, $id, $tag_ids) {
  set_children($table, $fk, $id,
    array_map(fn($tag_id) => ['tag_id' => $tag_id], $tag_ids));
}

function get_tag($id) {
  return one('SELECT * FROM tags WHERE id = ?', [$id]);
}

function get_tag_by_label($label) {
  return one('SELECT * FROM tags WHERE label = ?', [$label]);
}

function delete_tag($id) {
  return exec_query('DELETE FROM tags WHERE id = ?', [$id]);
}

// Ordering

function prepend_order($table) {
  $first = one('SELECT MIN(position) AS position FROM ' . $table);

  if($first['position'] === false) return 0;
  if($first['position'] > 0) return $first['position'] - 1;

  // If this fails, too bad, the ordering is a bit messed up, but
  // not the end of the world.
  exec_query('UPDATE ' . $table . ' SET position = position + 1', []);
  
  return 0;
}

function append_order($table) {
  $last = one('SELECT COALESCE(MAX(position), 0) AS position FROM ' . $table);
  return $last['position'] + 1;
}

// Calendars

function put_calendar($title, $subtitle, $color) {
  $ok = exec_query('INSERT INTO calendars (
    id,
    title,
    subtitle,
    color,
    position
  ) VALUES (?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $title,
    $subtitle,
    $color,
    append_order('sources')
  ]);

  return $ok ? $id : null;
}

function update_calendar($id, $title, $subtitle, $color, $position = null) {
  return exec_query('UPDATE calendars SET
    title = ?,
    subtitle = ?,
    color = ?,
    position = COALESCE(?, position)
  WHERE id = ?', [$title, $subtitle, $color, $position, $id]);
}

function list_calendars() {
  return all('SELECT * FROM calendars ORDER BY position ASC, title ASC');
}

function get_calendar($id) {
  return one('SELECT * FROM calendars WHERE id = ?', [$id]);
}

function get_oldest_calendar() {
  return one('SELECT id FROM calendars ORDER BY rowid');
}

function delete_calendar($id) {
  return exec_query('DELETE FROM calendars WHERE id = ?', [$id]);
}

// Subscriptions

function put_subscription($title, $subtitle, $url, $color, $filter = null) {
  $ok = exec_query('INSERT INTO subscriptions (
    id,
    title,
    subtitle,
    url,
    color,
    filter,
    position
  ) VALUES (?, ?, ?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $title,
    $subtitle,
    $url,
    $color,
    $filter,
    append_order('sources')
  ]);

  return $ok ? $id : null;
}

function update_subscription($id, $title, $subtitle, $url, $color, $filter = null) {
  return exec_query('UPDATE subscriptions SET
    title = ?,
    subtitle = ?,
    url = ?,
    color = ?,
    filter = ?
  WHERE id = ?', [$title, $subtitle, $url, $color, $filter, $id]);
}

function list_subscriptions() {
  return all('SELECT * FROM subscriptions ORDER BY position ASC, title ASC');
}

function get_subscription($id) {
  return one('SELECT * FROM subscriptions WHERE id = ?', [$id]);
}

function delete_subscription($id) {
  return exec_query('DELETE FROM subscriptions WHERE id = ?', [$id]);
}

// Sources

function list_sources() {
  return all('SELECT * FROM sources ORDER BY position ASC, title ASC');
}

function reorder_sources($sources) {
  foreach(array_values($sources) as $order => $source) {
    [$type, $id] = explode(':', $source, 2) + [null, null];

    $table = match($type) {
      'calendar' => 'calendars',
      'subscription' => 'subscriptions',
      default => null
    };

    if(!$table || !exec_query("UPDATE $table
      SET position = ? WHERE id = ?", [$order, $id])) return false;
  }

  return true;
}

function reorder_source_by_type($type, $ids) {
  in_array($type, ['calendars', 'subscriptions']) or die("type $type does not exist");

  $slots = array_column(all("SELECT position FROM $type ORDER BY position ASC, title ASC") ?: [], 'position');

  foreach(array_values($ids) as $i => $id) {
    $ok = exec_query("UPDATE $type
      SET position = ? WHERE id = ?", [$slots[$i] ?? $i, $id]);

    if(!$ok) return false;
  }

  return true;
}

// Appointments

function put_calendar_appointment(
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
  return exec_query('INSERT INTO appointments (
    id,
    calendar_id,
    subscription_id,
    title,
    content,
    starts_at,
    ends_at,
    location,
    meeting,
    recurrence,
    all_day,
    going,
    urgent,
    travel_before,
    travel_after
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

function put_subscription_appointment(
  $id,
  $subscription_id,
  $title,
  $content,
  $starts_at,
  $ends_at,
  $location,
  $meeting,
  $all_day,
  $recurrence
) {
  return exec_query('INSERT INTO appointments (
    id,
    subscription_id,
    title,
    content,
    starts_at,
    ends_at,
    location,
    meeting,
    all_day,
    recurrence
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    $id,
    $subscription_id,
    $title,
    $content,
    $starts_at,
    $ends_at,
    $location,
    $meeting,
    $all_day,
    $recurrence
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
  return exec_query('UPDATE appointments SET
    calendar_id = COALESCE(?, calendar_id),
    title = ?,
    content = ?,
    starts_at = ?,
    ends_at = ?,
    location = ?,
    meeting = ?,
    recurrence = ?,
    all_day = ?,
    going = ?,
    urgent = ?,
    travel_before = ?,
    travel_after = ?
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
  return exec_query('UPDATE appointments SET
    starts_at = ?,
    ends_at = ?
  WHERE id = ?', [$starts_at, $ends_at, $id]);
}

function update_appointment_meta($id, $going, $urgent, $travel_before = 0, $travel_after = 0) {
  return exec_query('UPDATE appointments SET
    going = ?,
    urgent = ?,
    travel_before = ?,
    travel_after = ?
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
  $recurrence
) {
  return exec_query('UPDATE appointments SET
    title = ?,
    content = ?,
    starts_at = ?,
    ends_at = ?,
    location = ?,
    meeting = ?,
    all_day = ?,
    recurrence = ?
  WHERE id = ?', [
    $title,
    $content,
    $starts_at,
    $ends_at,
    $location,
    $meeting,
    $all_day,
    $recurrence,
    $id
  ]);
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
    s.filter AS subscription_filter
  FROM appointments a
  LEFT JOIN calendars c ON c.id = a.calendar_id
  LEFT JOIN subscriptions s ON s.id = a.subscription_id
  WHERE a.recurrence IS NULL AND a.starts_at < ? AND a.ends_at > ?
  ORDER BY a.starts_at', [$to, $from]);
}

function list_calendar_appointments() {
  return all('SELECT * FROM appointments
    WHERE subscription_id IS NULL ORDER BY id') ?? [];
}

function list_subscription_appointments() {
  return all('SELECT
    appointments.*,
    subscriptions.filter AS subscription_filter
  FROM appointments
  JOIN subscriptions ON subscriptions.id = appointments.subscription_id
  WHERE appointments.calendar_id IS NULL
  ORDER BY appointments.id') ?? [];
}

function list_appointments_by_calendar($calendar_id) {
  return all('SELECT * FROM appointments
    WHERE calendar_id = ?', [$calendar_id]);
}

function list_appointments_by_subscription($subscription_id) {
  return all('SELECT * FROM appointments
    WHERE subscription_id = ?', [$subscription_id]);
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
    s.filter AS subscription_filter
  FROM appointments a
  LEFT JOIN calendars c ON c.id = a.calendar_id
  LEFT JOIN subscriptions s ON s.id = a.subscription_id
  WHERE a.recurrence IS NOT NULL
    AND a.starts_at < ?
  ORDER BY a.starts_at', [$to]);
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
    s.filter AS subscription_filter
  FROM appointments a
  LEFT JOIN calendars c ON c.id = a.calendar_id
  LEFT JOIN subscriptions s ON s.id = a.subscription_id
  WHERE a.id = ?', [$id]);
}

function delete_appointment($id) {
  return exec_query('DELETE FROM appointments WHERE id = ?', [$id]);
}

// Habits

function put_habit($title, $every, $color, $icon) {
  $ok = exec_query('INSERT INTO habits (
    id,
    title,
    every,
    color,
    icon
  ) VALUES (?, ?, ?, ?, ?)', [
    $id = generate_humid(),
    $title,
    $every,
    $color,
    $icon
  ]);

  return $ok ? $id : null;
}

function update_habit($id, $title, $every, $color, $icon) {
  return exec_query('UPDATE habits SET
    title = ?,
    every = ?,
    color = ?,
    icon = ?
  WHERE id = ?', [$title, $every, $color, $icon, $id]);
}

function list_habits() {
  return all('SELECT * FROM habits ORDER BY title');
}

function get_habit($id) {
  return one('SELECT * FROM habits WHERE id = ?', [$id]);
}

function delete_habit($id) {
  return exec_query('DELETE FROM habits WHERE id = ?', [$id]);
}

function list_habit_logs($from, $to) {
  return all('SELECT habit_id, DATE(changed_at) AS date
    FROM habit_log
    WHERE changed_at >= ? AND changed_at < ?
    ORDER BY changed_at', [$from, $to]);
}

function get_habit_log($habit_id, $date) {
  return one('SELECT * FROM habit_log
    WHERE habit_id = ? AND DATE(changed_at) = ?', [$habit_id, $date]);
}

function log_habit($habit_id, $date) {
  if(get_habit_log($habit_id, $date)) return true;

  $id = @one('SELECT MAX(id) + 1 AS id FROM habit_log')['id'] ?: 1;

  return exec_query('INSERT INTO habit_log (
    id,
    habit_id,
    changed_at
  ) VALUES (?, ?, ?)', [$id, $habit_id, "$date 00:00:00"]);
}

function unlog_habit($habit_id, $date) {
  return exec_query('DELETE FROM habit_log
    WHERE habit_id = ? AND DATE(changed_at) = ?', [$habit_id, $date]);
}

// Contacts

define('ENUM_SOCIAL_TYPE', ['instagram', 'discord', 'snapchat', 'airbuds', 'tiktok', 'github', 'codeberg', 'gitlab', 'linkedin', 'matrix', 'pinterest', 'twitter', 'youtube', 'facebook', 'activitypub', 'bsky']);

function list_contacts() {
  return all("SELECT contacts.*,
    (SELECT GROUP_CONCAT(tags.label, ' ')
      FROM tags
      JOIN contacts_tags ON contacts_tags.tag_id = tags.id
      WHERE contacts_tags.contact_id = contacts.id
    ) AS tag_labels,
    (SELECT GROUP_CONCAT(contacts_tags.tag_id, ' ')
      FROM contacts_tags
      WHERE contacts_tags.contact_id = contacts.id
    ) AS tag_ids,
    (SELECT GROUP_CONCAT(contact_emails.email, ' ')
      FROM contact_emails
      WHERE contact_emails.contact_id = contacts.id
    ) AS emails,
    (SELECT GROUP_CONCAT(contact_phone_numbers.phone_number, ' ')
      FROM contact_phone_numbers
      WHERE contact_phone_numbers.contact_id = contacts.id
    ) AS phone_numbers,
    (SELECT GROUP_CONCAT(contact_socials.handle, ' ')
      FROM contact_socials
      WHERE contact_socials.contact_id = contacts.id
    ) AS handles,
    (SELECT GROUP_CONCAT(contact_roles.organisation, ' ')
      FROM contact_roles
      WHERE contact_roles.contact_id = contacts.id
    ) AS org_names FROM contacts") ?? [];
}

function get_contact($id) {
  $contact = one('SELECT * FROM contacts WHERE id = ?', [$id]);

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
  return all('SELECT * FROM contact_emails WHERE contact_id = ?', [$id]);
}

function list_contact_phone_numbers($id) {
  return all('SELECT * FROM contact_phone_numbers WHERE contact_id = ?', [$id]);
}

function list_contact_urls($id) {
  return all('SELECT * FROM contact_urls WHERE contact_id = ?', [$id]);
}

function list_contact_socials($id) {
  return all('SELECT * FROM contact_socials WHERE contact_id = ?', [$id]);
}

function list_contact_roles($id) {
  return all('SELECT * FROM contact_roles WHERE contact_id = ?', [$id]);
}

function list_contact_addresses($id) {
  return all('SELECT a.*, ca.label AS link_label FROM contact_addresses ca
    JOIN addresses a ON a.id = ca.address_id WHERE ca.contact_id = ?', [$id]);
}

function list_contact_tags($id) {
  $tags = all('SELECT t.* FROM tags t
    JOIN contacts_tags ct ON ct.tag_id = t.id WHERE ct.contact_id = ?
    ORDER BY t.position ASC, t.id DESC', [$id]);

  return $tags === false ? false : inherit_tag_colors($tags);
}

function put_contact(
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

  $ok = exec_query('INSERT INTO contacts
    (display_name, first_name, middle_name, infix, last_name, birth_day, birth_month, birth_year, note)
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

  return exec_query('UPDATE contacts SET
    display_name = ?, first_name = ?, middle_name = ?,
    infix = ?, last_name = ?, birth_day = ?, birth_month = ?, birth_year = ?, note = ? WHERE id = ?',
    [$display_name, $first_name, $middle_name, $infix, $last_name, $birth_day, $birth_month, $birth_year, $note, $id]);
}

function update_contact_note($id, $note) {
  return exec_query('UPDATE contacts SET note = ? WHERE id = ?', [$note, $id]);
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

function get_contact_tags($id) {
  return tags_of('contacts_tags', 'contact_id', $id);
}

function set_contact_tags($id, $tag_ids) {
  set_tags('contacts_tags', 'contact_id', $id, $tag_ids);
}

function set_contact_addresses($id, $rows) {
  exec_query('DELETE FROM contact_addresses WHERE contact_id = ?', [$id]);

  foreach($rows as $row) {
    $address_id = put_address(
      label: null,
      street_name: $row['street_name'],
      street_number: $row['street_number'],
      postal_code: $row['postal_code'],
      city: $row['city'],
      province: $row['province'],
      country: $row['country'],
      timezone: $row['timezone']
    );

    exec_query('INSERT INTO contact_addresses (contact_id, label, address_id) VALUES (?, ?, ?)',
      [$id, $row['label'], $address_id]);
  }
}

function delete_contact($id) {
  return exec_query('DELETE FROM contacts WHERE id = ?', [$id]);
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
    (SELECT GROUP_CONCAT(orgs_tags.tag_id, ' ')
      FROM orgs_tags
      WHERE orgs_tags.org_id = organisations.id
    ) AS tag_ids,
    (SELECT GROUP_CONCAT(org_emails.email, ' ')
      FROM org_emails
      WHERE org_emails.org_id = organisations.id
    ) AS emails,
    (SELECT GROUP_CONCAT(org_phone_numbers.phone_number, ' ')
      FROM org_phone_numbers
      WHERE org_phone_numbers.org_id = organisations.id
    ) AS phone_numbers,
    (SELECT GROUP_CONCAT(org_socials.handle, ' ')
      FROM org_socials
      WHERE org_socials.org_id = organisations.id
    ) AS handles FROM organisations") ?? [];
}

function get_organisation($id) {
  $organisation = one('SELECT * FROM organisations WHERE id = ?', [$id]);

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
  return all('SELECT * FROM org_emails WHERE org_id = ?', [$id]);
}

function list_organisation_phone_numbers($id) {
  return all('SELECT * FROM org_phone_numbers WHERE org_id = ?', [$id]);
}

function list_organisation_urls($id) {
  return all('SELECT * FROM org_urls WHERE org_id = ?', [$id]);
}

function list_organisation_socials($id) {
  return all('SELECT * FROM org_socials WHERE org_id = ?', [$id]);
}

function list_organisation_addresses($id) {
  return all('SELECT a.*, oa.label AS link_label FROM org_addresses oa
    JOIN addresses a ON a.id = oa.address_id WHERE oa.org_id = ?', [$id]);
}

function list_organisation_tags($id) {
  $tags = all('SELECT t.* FROM tags t
    JOIN orgs_tags ot ON ot.tag_id = t.id WHERE ot.org_id = ?
    ORDER BY t.position ASC, t.id DESC', [$id]);

  return $tags === false ? false : inherit_tag_colors($tags);
}

function put_organisation($display_name, $legal_name, $registration_number, $vat_number, $note) {
  $ok = exec_query('INSERT INTO organisations
    (display_name, legal_name, registration_number, vat_number, note)
    VALUES (?, ?, ?, ?, ?)',
    [$display_name, $legal_name, $registration_number, $vat_number, $note]);

  return $ok ? DBH->lastInsertId() : null;
}

function update_organisation($id, $display_name, $legal_name, $registration_number, $vat_number, $note) {
  return exec_query('UPDATE organisations SET
    display_name = ?,
    legal_name = ?,
    registration_number = ?,
    vat_number = ?,
    note = ? WHERE id = ?',
    [$display_name, $legal_name, $registration_number, $vat_number, $note, $id]);
}

function update_organisation_note($id, $note) {
  return exec_query('UPDATE organisations SET note = ? WHERE id = ?', [$note, $id]);
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
  exec_query('DELETE FROM org_addresses WHERE org_id = ?', [$id]);

  foreach($rows as $row) {
    $address_id = put_address(
      label: null,
      street_name: $row['street_name'],
      street_number: $row['street_number'],
      postal_code: $row['postal_code'],
      city: $row['city'],
      province: $row['province'],
      country: $row['country'],
      timezone: $row['timezone']
    );

    exec_query('INSERT INTO org_addresses (org_id, label, address_id) VALUES (?, ?, ?)',
      [$id, $row['label'], $address_id]);
  }
}

function delete_organisation($id) {
  return exec_query('DELETE FROM organisations WHERE id = ?', [$id]);
}

function set_children($table, $fk, $id, $rows) {
  exec_query("DELETE FROM $table WHERE $fk = ?", [$id]);

  foreach($rows as $row) {
    $cols = array_keys($row);
    $names = implode(", ", array_map(fn($c) => "$c", [$fk, ...$cols]));
    $marks = implode(", ", array_fill(0, count($cols) + 1, "?"));
    exec_query("INSERT INTO $table ($names) VALUES ($marks)", [$id, ...array_values($row)]);
  }
}

// Addresses

function list_addresses() {
  return all("SELECT * FROM addresses
    ORDER BY CASE WHEN label IS NULL OR label = '' THEN 1 ELSE 0 END, city, street_name");
}

function get_address($id) {
  return one("SELECT * FROM addresses WHERE id = ?", [$id]);
}

function put_address(
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

  $existing = one('SELECT id FROM addresses
    WHERE street_name = ? AND street_number = ? AND postal_code = ? AND city = ? AND country = ?',
    [$street_name, $street_number, $postal_code, $city, $country]);

  if($existing) return $existing['id'];

  exec_query('INSERT INTO addresses (
    label,
    street_name,
    street_number,
    postal_code,
    city,
    province,
    country,
    timezone
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
  return exec_query('UPDATE addresses SET
    label = ?,
    street_name = ?,
    street_number = ?,
    postal_code = ?,
    city = ?,
    province = ?,
    country = ?,
    timezone = ?
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
  return exec_query('DELETE FROM addresses WHERE id = ?', [$id]);
}

// CalDAV helpers

function list_caldav_resources() {
  return all('SELECT * FROM caldav_resources ORDER BY entity_type, entity_id');
}

function list_caldav_resources_by_collection($collection) {
  return all('SELECT * FROM caldav_resources
    WHERE collection = ? ORDER BY href', [$collection]);
}

function get_caldav_resource($type, $id) {
  return one('SELECT * FROM caldav_resources
    WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
}

function get_caldav_resource_by_uid($uid) {
  return one('SELECT * FROM caldav_resources WHERE uid = ?', [$uid]);
}

function get_caldav_resource_by_href($collection, $href) {
  return one('SELECT * FROM caldav_resources
    WHERE collection = ? AND href = ?', [$collection, $href]);
}

function update_caldav_resource($type, $id, $href, $collection, $uid = null) {
  $resource = get_caldav_resource($type, $id);
  $uid ??= @$resource['uid'] ?: $id;
  $revision = @$resource['revision'] ?: 0;
  $touched_at = @$resource['touched_at'] ?: gmdate('c');

  exec_query('DELETE FROM caldav_resources
    WHERE entity_type = ? AND entity_id = ?', [$type, $id]);

  return exec_query('INSERT INTO caldav_resources (
    entity_type, entity_id, uid, href, collection, revision, touched_at
  ) VALUES (?, ?, ?, ?, ?, ?, ?)', [
    $type, $id, $uid, $href, $collection, $revision, $touched_at
  ]);
}

function touch_caldav_resource($type, $id) {
  return exec_query('UPDATE caldav_resources SET
    revision = revision + 1,
    touched_at = ?
    WHERE entity_type = ? AND entity_id = ?', [gmdate('c'), $type, $id]);
}

function delete_caldav_resource($type, $id) {
  return exec_query('DELETE FROM caldav_resources
    WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
}

// CalDAV alarms

function list_alarms($type, $id) {
  $key = match($type) {
    'appointment' => 'appointment_id',
    'task' => 'task_id',
    'wish' => 'wish_id',
    default => null
  };
  return $key ? all("SELECT * FROM alarms WHERE $key = ? ORDER BY id", [$id]) ?? [] : [];
}

function replace_alarms($type, $id, $alarms) {
  $key = match($type) {
    'appointment' => 'appointment_id',
    'task' => 'task_id',
    'wish' => 'wish_id',
    default => null
  };
  if(!$key || !exec_query("DELETE FROM alarms WHERE $key = ?", [$id])) return false;

  foreach($alarms as $alarm) {
    $values = [
      'appointment_id' => null,
      'task_id' => null,
      'wish_id' => null,
    ];
    $values[$key] = $id;

    if(!exec_query('INSERT INTO alarms (
      id, appointment_id, task_id, wish_id, trigger_at,
      trigger_offset, relative_to, description
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
      @$alarm['id'] ?: generate_humid(),
      $values['appointment_id'],
      $values['task_id'],
      $values['wish_id'],
      $alarm['trigger_at'],
      $alarm['trigger_offset'],
      $alarm['relative_to'],
      $alarm['description'],
    ])) return false;
  }

  return true;
}

// CalDAV properties

function list_properties($type, $id) {
  $key = match($type) {
    'appointment' => 'appointment_id',
    'task' => 'task_id',
    'wish' => 'wish_id',
    'alarm' => 'alarm_id',
    default => null
  };
  return $key ? all("SELECT * FROM properties WHERE $key = ? ORDER BY position, id", [$id]) ?? [] : [];
}

function replace_properties($type, $id, $properties) {
  $key = match($type) {
    'appointment' => 'appointment_id',
    'task' => 'task_id',
    'wish' => 'wish_id',
    'alarm' => 'alarm_id',
    default => null
  };
  if(!$key || !exec_query("DELETE FROM properties WHERE $key = ?", [$id])) return false;

  foreach(array_values($properties) as $position => $property) {
    $values = [
      'appointment_id' => null,
      'task_id' => null,
      'wish_id' => null,
      'alarm_id' => null,
    ];
    $values[$key] = $id;

    if(!exec_query('INSERT INTO properties (
      appointment_id, task_id, wish_id, alarm_id,
      name, parameters, value, position
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
      $values['appointment_id'],
      $values['task_id'],
      $values['wish_id'],
      $values['alarm_id'],
      strtoupper($property['name']),
      json_encode($property['parameters'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      $property['value'],
      $position,
    ])) return false;
  }

  return true;
}

// CalDAV change tracking

function caldav_global_revision() {
  return (int) (@one('SELECT revision FROM caldav_revision WHERE id = 1')['revision'] ?: 0);
}

function caldav_collection_revision($collection) {
  return (int) (@one('SELECT MAX(revision) AS revision FROM caldav_changes
    WHERE collection = ?', [$collection])['revision'] ?: 0);
}

function put_caldav_changes($changes) {
  if(!exec_query('UPDATE caldav_revision SET revision = revision + 1 WHERE id = 1', [])) return false;
  $revision = caldav_global_revision();

  foreach($changes as $change) {
    if(!exec_query('INSERT INTO caldav_changes (
      revision, collection, href, operation
    ) VALUES (?, ?, ?, ?)', [
      $revision,
      $change['collection'],
      $change['href'],
      $change['operation'],
    ])) return false;

  }

  return $revision;
}

function list_caldav_changes($collection, $revision) {
  return all('SELECT c.* FROM caldav_changes c
    WHERE c.collection = ? AND c.revision > ?
      AND NOT EXISTS (
        SELECT 1 FROM caldav_changes newer
        WHERE newer.collection = c.collection
          AND newer.href = c.href
          AND newer.revision > c.revision
      )
    ORDER BY c.revision, c.href', [$collection, $revision]) ?? [];
}

// Configuration

function config() {
  $map = [];
  $rows = all("SELECT * FROM config");

  foreach($rows as $row)
    $map[$row['property']] = $row['value'];

  return $map;
}

function update_config($property, $value) {
  // We delete first, because otherwise we need to differentiate on adapter to
  // use different syntax (ON CONFLICT, ON DUPLICATE KEY etc.) and that is a headache.
  // We also don't care if this first query succeeds (bc yk it might not exist).

  exec_query('DELETE FROM config WHERE property = ?', [$property]);

  if($value === null) return true;

  return exec_query('INSERT INTO config (property, value) VALUES (?, ?)', [$property, $value]);
}

// Audit log

function put_log($table_name, $record_id, $message, $author) {
  return exec_query('INSERT INTO audit_log (
    table_name,
    record_id,
    message,
    author
  ) VALUES (?, ?, ?, ?)', [$table_name, $record_id, $message, $author]);
}

function list_logs($table_name, $record_id) {
  return all('SELECT * FROM audit_log
    WHERE table_name = ? AND record_id = ?
    ORDER BY changed_at ASC, id ASC', [$table_name, $record_id]) ?? [];
}

function get_log_dates($table, $id) {
  return one('SELECT
    MIN(changed_at) AS created_at,
    MAX(changed_at) AS modified_at
    FROM audit_log WHERE table_name = ? AND record_id = ?', [$table, $id]);
}

// Migrations

function version() {
  $latest = one('SELECT * FROM migrations ORDER BY version DESC');
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
    exec_query('INSERT INTO migrations (version) VALUES (?)', [$version])
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
  return !!one("SELECT slug FROM $table WHERE slug = ?", [$slug]);
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

// Transactions

function transaction($callback) {
  $nested = DBH->inTransaction();
  if(!$nested) DBH->beginTransaction();

  try {
    $result = $callback();
    if(!$nested) DBH->commit();
    return $result;
  }
  catch(\Throwable $e) {
    if(!$nested && DBH->inTransaction()) DBH->rollBack();
    throw $e;
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
