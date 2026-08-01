<?php
// SQL-based store.

namespace store;

$_URL = getenv("DATABASE_URL") ?: "sqlite://data.db";

if(str_starts_with($_URL, "sqlite://")) {
  $_DATABASE = ['scheme' => 'sqlite', 'path' => substr($_URL, strlen("sqlite://"))];
}
else {
  $_DATABASE = parse_url($_URL) or fail("Syntax error in database connection string.");
}

switch($_DATABASE['scheme']) {
  case 'mysql': require __DIR__ . "/store/adapter/mysql.php"; break;
  case 'postgres': require __DIR__ . "/store/adapter/postgres.php"; break;
  case 'sqlite': require __DIR__ . "/store/adapter/sqlite.php"; break;
  default: fail("Unsupported database driver '{$_DATABASE['scheme']}'.");
}

define('ENUM_TIMEZONE', \DateTimeZone::listIdentifiers());
define('ENUM_TASK_STATUS', ['todo', 'wip', 'backlog', 'blocked', 'done', 'nvm']);
define('ENUM_WISH_STATUS', ['dream', 'bought', 'nvm']);
define('ENUM_SSL_MODE', ['plain', 'tls', 'ssl']);

// Notes

function put_note($title, $content, $date = null) {
  exec_query('INSERT INTO notes (
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

  return $id;
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

function list_note_tags($id) {
  return tags_of('notes_tags', 'note_id', $id);
}

function list_note_tag_ids($id) {
  return array_column(list_note_tags($id), 'id');
}

function set_note_tags($id, $tag_ids) {
  set_tags('notes_tags', 'note_id', $id, $tag_ids);
}

function notes_query($query, $stable = false) {
  [$tags, $terms] = \core\parse_query($query);

  $where = [];
  $params = [];

  foreach($terms as $term) {
    $where[] = '(EXO_NORMALIZE(title) LIKE EXO_NORMALIZE(?)
      OR EXO_NORMALIZE(content) LIKE EXO_NORMALIZE(?))';
    $like = "%$term%";
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
  if($stable) $sql .= ', id DESC';

  return [$sql, $params];
}

function list_notes($query = "") {
  [$sql, $params] = notes_query($query);
  return all($sql, $params);
}

function list_notes_paginated($query, $limit, $offset = 0) {
  [$sql, $params] = notes_query($query, stable: true);
  return paginate($sql, $limit, offset: $offset, params: $params);
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
  if(!in_array($status, ENUM_TASK_STATUS)) fail("status $status does not exist");

  $open_at ??= gmdate('c');

  exec_query('INSERT INTO tasks (
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

  exec_query('INSERT INTO task_log (
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

  return $id;
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
  if(!in_array($status, ENUM_TASK_STATUS)) fail("status $status does not exist");

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

function list_task_tags($id) {
  return tags_of('tasks_tags', 'task_id', $id);
}

function list_task_tag_ids($id) {
  return array_column(list_task_tags($id), 'id');
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
  $overdue = null;

  foreach(explode(" ", $query) as $segment) {
    $parts = explode(":", $segment);
    if(count($parts) != 2) continue;
    [$selector, $value] = $parts;

    if($value == 'urgent') $urgent = $selector == "is";
    elseif($value == 'expired') $expired = $selector == 'is';
    elseif($value == 'overdue') $overdue = $selector == 'is';
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
  $now = new \DateTimeImmutable("now", new \DateTimeZone(TIMEZONE));

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

    $due = $task['next'] ? new \DateTimeImmutable($task['next']) : null;
    if($due && cast_boolean($task['due_all_day'])) {
      $due = $due->setTimezone(new \DateTimeZone(TIMEZONE))->modify('+1 day');
    }
    $is_overdue = $due && $due <= $now;

    if($overdue !== null && $is_overdue != $overdue) continue;
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

  $task['tags'] = array_column($tags, 'label');

  return $task;
}

function get_task_log($id) {
  return all('SELECT * FROM task_log WHERE task_id = ? ORDER BY changed_at ASC, id ASC', [$id]);
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
  if(!in_array($status, ENUM_WISH_STATUS)) fail("status $status does not exist");

  exec_query('INSERT INTO wishes (
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

  exec_query('INSERT INTO wish_log (
    wish_id,
    status
  ) VALUES (?, ?)', [$id, $status]);

  return $id;
}

function set_wish_status($id, $status, $comment = "") {
  if(!in_array($status, ENUM_WISH_STATUS)) fail("status $status does not exist");

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
  return all('SELECT url, price FROM wish_urls WHERE wish_id = ?', [$id]);
}

function set_wish_urls($id, $rows) {
  set_children('wish_urls', 'wish_id', $id, $rows);
}

function list_wish_tags($id) {
  return tags_of('wishes_tags', 'wish_id', $id);
}

function list_wish_tag_ids($id) {
  return array_column(list_wish_tags($id), 'id');
}

function set_wish_tags($id, $tag_ids) {
  set_tags('wishes_tags', 'wish_id', $id, $tag_ids);
}

function get_wish_log($id) {
  return all('SELECT * FROM wish_log WHERE wish_id = ? ORDER BY changed_at ASC', [$id]);
}

function wishes_query($statuses = [], $override = []) {
  $params = [];
  $where = [];

  if($statuses) {
    $status = 'log.status IN (' . join(', ', array_fill(0, count($statuses), '?')) . ')';
    $params = [...$params, ...$statuses];

    if($override) {
      $status = "($status OR wishes.id IN (" . join(', ', array_fill(0, count($override), '?')) . '))';
      $params = [...$params, ...$override];
    }

    $where[] = $status;
  }

  $sql = "SELECT
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
      )";

  if($where) $sql .= ' WHERE ' . join(' AND ', $where);
  $sql .= ' ORDER BY wishes.added_at DESC, wishes.id DESC';

  return [$sql, $params];
}

function list_wishes($statuses = [], $override = []) {
  [$sql, $params] = wishes_query($statuses, $override);
  return all($sql, $params);
}

function list_wishes_paginated($statuses, $override, $limit, $offset = 0) {
  [$sql, $params] = wishes_query($statuses, $override);
  return paginate($sql, $limit, offset: $offset, params: $params);
}

function delete_wish($id) {
  return exec_query('DELETE FROM wishes WHERE id = ?', [$id]);
}

// Bookmarks

function put_bookmark($url, $label = null, $note = null, $favicon = null, $date = null) {
  exec_query('INSERT INTO bookmarks (
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

  return $id;
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

function list_bookmark_tags($id) {
  return tags_of('bookmarks_tags', 'bookmark_id', $id);
}

function list_bookmark_tag_ids($id) {
  return array_column(list_bookmark_tags($id), 'id');
}

function set_bookmark_tags($id, $tag_ids) {
  set_tags('bookmarks_tags', 'bookmark_id', $id, $tag_ids);
}

function get_bookmark($id) {
  return one('SELECT * FROM bookmarks WHERE id = ?', [$id]);
}

function bookmarks_query($query, $stable = false) {
  [$tags, $terms] = \core\parse_query($query);

  $where = [];
  $params = [];

  foreach($terms as $term) {
    $where[] = '(EXO_NORMALIZE(label) LIKE EXO_NORMALIZE(?)
      OR EXO_NORMALIZE(url) LIKE EXO_NORMALIZE(?)
      OR EXO_NORMALIZE(note) LIKE EXO_NORMALIZE(?))';
    $like = "%$term%";
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
  if($stable) $sql .= ', id DESC';

  return [$sql, $params];
}

function list_bookmarks($query = "") {
  [$sql, $params] = bookmarks_query($query);
  return all($sql, $params);
}

function list_bookmarks_paginated($query, $limit, $offset = 0) {
  [$sql, $params] = bookmarks_query($query, stable: true);
  return paginate($sql, $limit, offset: $offset, params: $params);
}

function delete_bookmark($id) {
  return exec_query('DELETE FROM bookmarks WHERE id = ?', [$id]);
}

// Tracker

function put_timing($description, $starts_at, $ends_at, $task_id = null) {
  if($task_id) get_task($task_id) or fail("task with ID $task_id does not exist");

  exec_query('INSERT INTO timings (
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

  return $id;
}

function update_timing($id, $description, $starts_at, $ends_at, $task_id) {
  if($task_id) get_task($task_id) or fail("task with ID $task_id does not exist");

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

function list_timing_tags($id) {
  return tags_of('timings_tags', 'timing_id', $id);
}

function list_timings_tags($ids) {
  if(!$ids) return [];

  $placeholders = join(', ', array_fill(0, count($ids), '?'));

  return inherit_tag_colors(all("SELECT link.timing_id, tags.* FROM tags
    JOIN timings_tags link ON link.tag_id = tags.id
    WHERE link.timing_id IN ($placeholders)
    ORDER BY tags.position ASC, tags.id DESC", $ids));
}

function list_timing_tag_ids($id) {
  return array_column(list_timing_tags($id), 'id');
}

function set_timing_tags($id, $tag_ids) {
  set_tags('timings_tags', 'timing_id', $id, $tag_ids);
}

function list_timings() {
  return all('SELECT * FROM timings ORDER BY starts_at DESC');
}

function list_timings_paginated($limit, $offset = 0) {
  return paginate('SELECT * FROM timings ORDER BY starts_at DESC, id DESC', $limit, offset: $offset);
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

function list_timing_tags_between($from, $to) {
  return all('SELECT t.id, t.starts_at, t.ends_at, tt.tag_id
    FROM timings t
    JOIN timings_tags tt ON tt.timing_id = t.id
    WHERE t.starts_at < ? AND t.ends_at > ?', [$to, $from]);
}

function get_timing($id) {
  return one('SELECT * FROM timings WHERE id = ?', [$id]);
}

function delete_timing($id) {
  return exec_query('DELETE FROM timings WHERE id = ?', [$id]);
}

// Quotas

function list_quotas() {
  $quotas = all('SELECT q.*, t.label, t.color
    FROM quotas q
    JOIN tags t ON t.id = q.tag_id
    ORDER BY t.position ASC, t.id DESC');

  return inherit_tag_colors($quotas, id_key: 'tag_id');
}

function get_quota_by_tag($tag_id) {
  return one('SELECT * FROM quotas WHERE tag_id = ?', [$tag_id]);
}

function put_quota($tag_id, $period, $minutes, $start_date) {
  return exec_query('INSERT INTO quotas (tag_id, period, minutes, start_date)
    VALUES (?, ?, ?, ?)', [$tag_id, $period, $minutes, $start_date]);
}

function update_quota($tag_id, $period, $minutes, $start_date) {
  return exec_query('UPDATE quotas
    SET period = ?, minutes = ?, start_date = ? WHERE tag_id = ?',
    [$period, $minutes, $start_date, $tag_id]);
}

function delete_quota($tag_id) {
  return exec_query('DELETE FROM quotas WHERE tag_id = ?', [$tag_id]);
}

// Tags

function put_tag($label, $color, $parent_id) {
  if($parent_id) get_tag($parent_id) or fail("tag with ID $parent_id does not exist");

  exec_query('INSERT INTO tags (
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

  return DBH->lastInsertId();
}

function update_tag($id, $label, $color, $parent_id) {
  if($parent_id) {
    $cursor = $parent_id;
    while($cursor) {
      if($cursor == $id) fail("illegal circular structure detected");
      $tag = get_tag($cursor) or fail("tag with ID $cursor does not exist");
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
    exec_query('UPDATE tags
      SET position = ? WHERE id = ?', [$order, $id]);
  }

  return true;
}

function tags_of($table, $fk, $id) {
  $tags = all("SELECT tags.* FROM tags
    JOIN $table link ON link.tag_id = tags.id
    WHERE link.$fk = ?
    ORDER BY tags.position ASC, tags.id DESC", [$id]);

  return inherit_tag_colors($tags);
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

  if($first['position'] > 0) return $first['position'] - 1;

  exec_query('UPDATE ' . $table . ' SET position = position + 1', []);
  
  return 0;
}

function append_order($table) {
  $last = one('SELECT COALESCE(MAX(position), 0) AS position FROM ' . $table);
  return $last['position'] + 1;
}

// Calendars

function put_calendar($title, $subtitle, $color) {
  exec_query('INSERT INTO calendars (
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

  return $id;
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

function get_first_calendar() {
  return one('SELECT id FROM calendars ORDER BY position ASC, title ASC');
}

function delete_calendar($id) {
  return exec_query('DELETE FROM calendars WHERE id = ?', [$id]);
}

// Subscriptions

function put_subscription($title, $subtitle, $url, $color, $filter = null) {
  exec_query('INSERT INTO subscriptions (
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

  return $id;
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

function update_subscription_history($id, $history) {
  return exec_query('UPDATE subscriptions SET history = ? WHERE id = ?', [$history, $id]);
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

    if(!$table) fail("type $type does not exist");
    exec_query("UPDATE $table SET position = ? WHERE id = ?", [$order, $id]);
  }

  return true;
}

function reorder_source_by_type($type, $ids) {
  if(!in_array($type, ['calendars', 'subscriptions'])) fail("type $type does not exist");

  $slots = array_column(all("SELECT position FROM $type ORDER BY position ASC, title ASC"), 'position');

  foreach(array_values($ids) as $i => $id) {
    exec_query("UPDATE $type
      SET position = ? WHERE id = ?", [$slots[$i] ?? $i, $id]);
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
  exec_query('INSERT INTO appointments (
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
  ]);

  return $id;
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
    WHERE subscription_id IS NULL ORDER BY id');
}

function list_subscription_appointments() {
  return all('SELECT
    appointments.*,
    subscriptions.filter AS subscription_filter
  FROM appointments
  JOIN subscriptions ON subscriptions.id = appointments.subscription_id
  WHERE appointments.calendar_id IS NULL
  ORDER BY appointments.id');
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
  exec_query('INSERT INTO habits (
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

  return $id;
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

define('ENUM_SOCIAL_TYPE', ['instagram', 'discord', 'snapchat', 'spacehey', 'airbuds', 'tiktok', 'wattpad', 'github', 'codeberg', 'gitlab', 'linkedin', 'matrix', 'pinterest', 'twitter', 'youtube', 'facebook', 'activitypub', 'bsky']);

function list_contacts() {
  return all("SELECT contacts.*,
    (SELECT EXO_CONCAT(tags.label, ' ')
      FROM tags
      JOIN contacts_tags ON contacts_tags.tag_id = tags.id
      WHERE contacts_tags.contact_id = contacts.id
    ) AS tag_labels,
    (SELECT EXO_CONCAT(contacts_tags.tag_id, ' ')
      FROM contacts_tags
      WHERE contacts_tags.contact_id = contacts.id
    ) AS tag_ids,
    (SELECT EXO_CONCAT(contact_emails.email, ' ')
      FROM contact_emails
      WHERE contact_emails.contact_id = contacts.id
    ) AS emails,
    (SELECT EXO_CONCAT(contact_phone_numbers.phone_number, ' ')
      FROM contact_phone_numbers
      WHERE contact_phone_numbers.contact_id = contacts.id
    ) AS phone_numbers,
    (SELECT EXO_CONCAT(contact_socials.handle, ' ')
      FROM contact_socials
      WHERE contact_socials.contact_id = contacts.id
    ) AS handles,
    (SELECT EXO_CONCAT(organisations.display_name, ' ')
      FROM contact_roles
      JOIN organisations ON organisations.id = contact_roles.org_id
      WHERE contact_roles.contact_id = contacts.id
    ) AS org_names FROM contacts");
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
  return all('SELECT contact_roles.*, organisations.display_name AS organisation_name
    FROM contact_roles
    JOIN organisations ON organisations.id = contact_roles.org_id
    WHERE contact_roles.contact_id = ?', [$id]);
}

function list_contact_addresses($id) {
  return all('SELECT a.*, ca.label AS link_label FROM contact_addresses ca
    JOIN addresses a ON a.id = ca.address_id WHERE ca.contact_id = ?', [$id]);
}

function list_contact_tags($id) {
  $tags = all('SELECT t.* FROM tags t
    JOIN contacts_tags ct ON ct.tag_id = t.id WHERE ct.contact_id = ?
    ORDER BY t.position ASC, t.id DESC', [$id]);

  return inherit_tag_colors($tags);
}

function put_contact(
  $display_name,
  $first_name,
  $middle_name,
  $legal_infix,
  $legal_name,
  $family_infix,
  $family_name,
  $name_order,
  $birth_day,
  $birth_month,
  $birth_year,
  $timezone,
  $note
) {
  [$birth_day, $birth_month, $birth_year] = validate_birthday($birth_day, $birth_month, $birth_year);

  exec_query('INSERT INTO contacts
    (display_name, first_name, middle_name, legal_infix, legal_name, family_infix, family_name, name_order,
      birth_day, birth_month, birth_year, timezone, note)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$display_name, $first_name, $middle_name, $legal_infix, $legal_name, $family_infix, $family_name, $name_order,
      $birth_day, $birth_month, $birth_year, $timezone, $note]);

  return DBH->lastInsertId();
}

function update_contact(
  $id,
  $display_name,
  $first_name,
  $middle_name,
  $legal_infix,
  $legal_name,
  $family_infix,
  $family_name,
  $name_order,
  $birth_day,
  $birth_month,
  $birth_year,
  $timezone,
  $note
) {
  [$birth_day, $birth_month, $birth_year] = validate_birthday($birth_day, $birth_month, $birth_year);

  return exec_query('UPDATE contacts SET
    display_name = ?, first_name = ?, middle_name = ?, legal_infix = ?, legal_name = ?,
    family_infix = ?, family_name = ?, name_order = ?, birth_day = ?, birth_month = ?, birth_year = ?,
    timezone = ?, note = ? WHERE id = ?',
    [$display_name, $first_name, $middle_name, $legal_infix, $legal_name, $family_infix, $family_name, $name_order,
      $birth_day, $birth_month, $birth_year, $timezone, $note, $id]);
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

function list_contact_tag_ids($id) {
  return array_column(list_contact_tags($id), 'id');
}

function set_contact_tags($id, $tag_ids) {
  set_tags('contacts_tags', 'contact_id', $id, $tag_ids);
}

function set_contact_addresses($id, $rows) {
  set_address_links('contact_addresses', 'contact_id', $id, $rows);
}

function delete_contact($id) {
  return exec_query('DELETE FROM contacts WHERE id = ?', [$id]);
}

function validate_birthday($day, $month, $year) {
  if(($day === null) !== ($month === null)) fail("Invalid birthday.", status: 400);
  if($year !== null && $day === null) fail("Invalid birthday.", status: 400);
  if($day !== null && !checkdate($month, $day, $year ?: 2000)) fail("Invalid birthday.", status: 400);

  return [$day, $month, $year];
}

function list_organisations() {
  return all("SELECT organisations.*,
    (SELECT EXO_CONCAT(tags.label, ' ')
      FROM tags
      JOIN orgs_tags ON orgs_tags.tag_id = tags.id
      WHERE orgs_tags.org_id = organisations.id
    ) AS tag_labels,
    (SELECT EXO_CONCAT(orgs_tags.tag_id, ' ')
      FROM orgs_tags
      WHERE orgs_tags.org_id = organisations.id
    ) AS tag_ids,
    (SELECT EXO_CONCAT(org_emails.email, ' ')
      FROM org_emails
      WHERE org_emails.org_id = organisations.id
    ) AS emails,
    (SELECT EXO_CONCAT(org_phone_numbers.phone_number, ' ')
      FROM org_phone_numbers
      WHERE org_phone_numbers.org_id = organisations.id
    ) AS phone_numbers,
    (SELECT EXO_CONCAT(org_socials.handle, ' ')
      FROM org_socials
      WHERE org_socials.org_id = organisations.id
    ) AS handles FROM organisations");
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
  $organisation['employees'] = list_organisation_employees($id);

  return $organisation;
}

function list_organisation_employees($id) {
  return all('SELECT contacts.*, contact_roles.role
    FROM contact_roles
    JOIN contacts ON contacts.id = contact_roles.contact_id
    WHERE contact_roles.org_id = ?
      AND NOT EXISTS (
        SELECT 1 FROM contact_roles earlier
        WHERE earlier.org_id = contact_roles.org_id
          AND earlier.contact_id = contact_roles.contact_id
          AND earlier.id < contact_roles.id
      )
    ORDER BY contact_roles.id', [$id]);
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

  return inherit_tag_colors($tags);
}

function put_organisation($display_name, $legal_name, $registration_number, $vat_number, $timezone, $note) {
  exec_query('INSERT INTO organisations
    (display_name, legal_name, registration_number, vat_number, timezone, note)
    VALUES (?, ?, ?, ?, ?, ?)',
    [$display_name, $legal_name, $registration_number, $vat_number, $timezone, $note]);

  return DBH->lastInsertId();
}

function update_organisation($id, $display_name, $legal_name, $registration_number, $vat_number, $timezone, $note) {
  return exec_query('UPDATE organisations SET
    display_name = ?,
    legal_name = ?,
    registration_number = ?,
    vat_number = ?,
    timezone = ?,
    note = ? WHERE id = ?',
    [$display_name, $legal_name, $registration_number, $vat_number, $timezone, $note, $id]);
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
  set_address_links('org_addresses', 'org_id', $id, $rows);
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
    ORDER BY CASE WHEN label IS NULL OR label = '' THEN 1 ELSE 0 END, city, street_address");
}

function get_address($id) {
  return one("SELECT * FROM addresses WHERE id = ?", [$id]);
}

function find_address($street_address, $postal_code, $city, $country) {
  return one('SELECT id FROM addresses
    WHERE street_address = ? AND postal_code = ? AND city = ? AND country = ?',
    [$street_address, $postal_code, $city, $country]);
}

function put_address($label, $street_address, $postal_code, $city, $province, $country) {
  // If an address already exists verbatim, we reuse the existing address row.
  // This keeps the database free of duplicates.

  $existing = find_address($street_address, $postal_code, $city, $country);
  if($existing) return $existing['id'];

  exec_query('INSERT INTO addresses (
    label,
    street_address,
    postal_code,
    city,
    province,
    country
  ) VALUES (?, ?, ?, ?, ?, ?)', [
    $label,
    $street_address,
    $postal_code,
    $city,
    $province,
    $country
  ]);

  return DBH->lastInsertId();
}

function update_address($id, $label, $street_address, $postal_code, $city, $province, $country) {
  return exec_query('UPDATE addresses SET
    label = ?,
    street_address = ?,
    postal_code = ?,
    city = ?,
    province = ?,
    country = ?
  WHERE id = ?', [
    $label,
    $street_address,
    $postal_code,
    $city,
    $province,
    $country,
    $id
  ]);
}

function set_address_links($table, $fk, $id, $rows) {
  exec_query("DELETE FROM $table WHERE $fk = ?", [$id]);

  foreach($rows as $row) {
    if(!in_array($row['country'], \country_codes()))
      fail("Invalid address country.", status: 400);

    $current = @$row['id'] ? get_address($row['id']) : null;
    if(@$row['id'] && !$current) fail("Address not found.", status: 400);

    $existing = find_address(
      $row['street_address'],
      $row['postal_code'],
      $row['city'],
      $row['country']
    );

    if($current && (!$existing || $existing['id'] == $current['id'])) {
      update_address(
        $current['id'],
        $current['label'],
        $row['street_address'],
        $row['postal_code'],
        $row['city'],
        $row['province'],
        $row['country']
      );
      $address_id = $current['id'];
    }
    else {
      $address_id = put_address(
        label: null,
        street_address: $row['street_address'],
        postal_code: $row['postal_code'],
        city: $row['city'],
        province: $row['province'],
        country: $row['country']
      );
    }

    exec_query("INSERT INTO $table ($fk, label, address_id) VALUES (?, ?, ?)",
      [$id, $row['label'], $address_id]);
  }
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

  return $key ? all("SELECT * FROM alarms WHERE $key = ? ORDER BY id", [$id]) : [];
}

function replace_alarms($type, $id, $alarms) {
  $key = match($type) {
    'appointment' => 'appointment_id',
    'task' => 'task_id',
    'wish' => 'wish_id',
    default => null
  };
  if(!$key) fail("type $type does not support alarms");
  exec_query("DELETE FROM alarms WHERE $key = ?", [$id]);

  foreach($alarms as $alarm) {
    $values = [
      'appointment_id' => null,
      'task_id' => null,
      'wish_id' => null,
    ];
    $values[$key] = $id;

    exec_query('INSERT INTO alarms (
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
    ]);
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

  return $key ? all("SELECT * FROM properties WHERE $key = ? ORDER BY position, id", [$id]) : [];
}

function replace_properties($type, $id, $properties) {
  $key = match($type) {
    'appointment' => 'appointment_id',
    'task' => 'task_id',
    'wish' => 'wish_id',
    'alarm' => 'alarm_id',
    default => null
  };
  if(!$key) fail("type $type does not support properties");
  exec_query("DELETE FROM properties WHERE $key = ?", [$id]);

  foreach(array_values($properties) as $position => $property) {
    $values = [
      'appointment_id' => null,
      'task_id' => null,
      'wish_id' => null,
      'alarm_id' => null,
    ];
    $values[$key] = $id;

    exec_query('INSERT INTO properties (
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
    ]);
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
  exec_query('UPDATE caldav_revision SET revision = revision + 1 WHERE id = 1', []);
  $revision = caldav_global_revision();

  foreach($changes as $change) {
    exec_query('INSERT INTO caldav_changes (
      revision, collection, href, operation
    ) VALUES (?, ?, ?, ?)', [
      $revision,
      $change['collection'],
      $change['href'],
      $change['operation'],
    ]);
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
    ORDER BY c.revision, c.href', [$collection, $revision]);
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

// Logging

function put_audit_log($table_name, $record_id, $message, $author, $operation = 'update') {
  return exec_query('INSERT INTO audit_log (
    table_name,
    record_id,
    message,
    author,
    operation
  ) VALUES (?, ?, ?, ?, ?)', [$table_name, $record_id, $message, $author, $operation]);
}

function put_system_log($level, $message, $context = []) {
  $json = $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null;
  return exec_query('INSERT INTO system_logs (level, message, context) VALUES (?, ?, ?)', [$level, $message, $json]);
}

function put_http_log($request) {
  return exec_query('INSERT INTO http_logs (
    method, uri, status, authenticated, remote_addr, user_agent, referer,
    content_type, request_bytes, response_bytes, duration_ms
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    $request['method'],
    $request['uri'],
    $request['status'],
    $request['authenticated'],
    $request['remote_addr'],
    $request['user_agent'],
    $request['referer'],
    $request['content_type'],
    $request['request_bytes'],
    $request['response_bytes'],
    $request['duration_ms'],
  ]);
}

function logs_query($audit_only = false) {
  if($audit_only) return "SELECT changed_at, message, operation, author, table_name, record_id,
      'audit_log' AS source
    FROM audit_log
    ORDER BY changed_at DESC, id DESC";

  return "SELECT changed_at, message, operation, author, table_name, record_id,
      'audit_log' AS source
    FROM audit_log
    UNION ALL
    SELECT changed_at, 'CalDAV resource changed.', operation, 'caldav', collection, href,
      'caldav_changes'
    FROM caldav_changes
    ORDER BY changed_at DESC";
}

function list_logs() {
  return all(logs_query());
}

function list_logs_filtered($sources, $levels, $message, $from, $to, $limit) {
  $selects = [];
  $params = [];

  $where = function($column, $level = null) use ($levels, $message, $from, $to, &$params) {
    $conditions = [];

    if($level === null) {
      if(!$levels) return null;
      $marks = join(', ', array_fill(0, count($levels), '?'));
      $conditions[] = "level IN ($marks)";
      array_push($params, ...$levels);
    }
    elseif(!in_array($level, $levels)) return null;

    if($message) {
      $conditions[] = "EXO_NORMALIZE($column) LIKE EXO_NORMALIZE(?)";
      $params[] = "%$message%";
    }
    if($from) {
      $conditions[] = 'changed_at >= ?';
      $params[] = $from;
    }
    if($to) {
      $conditions[] = 'changed_at <= ?';
      $params[] = $to;
    }

    return $conditions ? ' WHERE ' . join(' AND ', $conditions) : '';
  };

  if(in_array('audit', $sources) && ($filters = $where('message', 'info')) !== null)
    $selects[] = "SELECT id AS source_id, changed_at, 'info' AS level, message, operation,
      author, table_name, record_id, 'audit' AS source, NULL AS http_status,
      NULL AS system_context FROM audit_log$filters";

  if(in_array('system', $sources) && ($filters = $where('message')) !== null)
    $selects[] = "SELECT id AS source_id, changed_at, level, message, '' AS operation,
      'system' AS author, '' AS table_name, '' AS record_id, 'system' AS source,
      NULL AS http_status, context AS system_context FROM system_logs$filters";

  if(in_array('http', $sources) && ($filters = $where('uri', 'debug')) !== null)
    $selects[] = "SELECT id AS source_id, changed_at, 'debug' AS level, uri AS message, method AS operation,
      remote_addr AS author, '' AS table_name, '' AS record_id, 'http' AS source,
      status AS http_status, NULL AS system_context FROM http_logs$filters";

  if(!$selects) return [];

  $query = join(' UNION ALL ', $selects);

  return paginate("SELECT logs.*,
      CASE WHEN source = 'audit' THEN ((SELECT operation FROM audit_log AS latest
        WHERE latest.table_name = logs.table_name AND latest.record_id = logs.record_id
        ORDER BY latest.changed_at DESC, latest.id DESC LIMIT 1) = 'delete') END AS deleted
      FROM ($query) AS logs
      ORDER BY changed_at DESC, source DESC, source_id DESC",
    $limit, params: $params);
}

function list_audit_logs($table_name, $record_id) {
  return all('SELECT * FROM audit_log
    WHERE table_name = ? AND record_id = ?
    ORDER BY changed_at ASC, id ASC', [$table_name, $record_id]);
}

function list_system_logs() {
  return all('SELECT * FROM system_logs ORDER BY changed_at DESC, id DESC');
}

function list_http_logs() {
  return all('SELECT * FROM http_logs ORDER BY changed_at DESC, id DESC');
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
  $pending = [];
  $migrations = glob(__DIR__ . "/store/migrations/v*.sql") ?: [];

  foreach ($migrations as $path) {
    $version = (int)substr(basename($path), 1); // The int cast stops at '_'.
    if ($version > $from && $version <= $to) $pending[$version] = $path;
  }

  ksort($pending, SORT_NUMERIC);

  foreach ($pending as $version => $path) {
    \adapter\execute($path);
    put_system_log('info', "Store schema migrated.", ['version' => $version]);

    // NOTE(robin): if the STORE_VERSION value is higher than any migration file
    // (aka the migration file has not been committed or is missing), this function
    // will run on EVERY REQUEST, because the database never catches up. Bad?
    exec_query('INSERT INTO migrations (version) VALUES (?)', [$version]);
  }
}

// SQL helpers

function one($sql, $params = []) {
  return exec_query("$sql LIMIT 1", $params)->fetch();
}

function all($sql, $params = []) {
  return exec_query($sql, $params)->fetchAll();
}

function paginate($sql, $limit, $offset = 0, $params = []) {
  return all("$sql LIMIT ? OFFSET ?", [...$params, (int)$limit, (int)$offset]);
}

function exec_query($sql, $params) {
  $sql = \adapter\expand_macros($sql);

  $query = DBH->prepare($sql);
  $query->execute($params);
  return $query;
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
  fail("Mismatched store versions: expected v" . STORE_VERSION . ",
  but store is already at v$current_store_version");
}

if($current_store_version < $latest_store_version) {
  migrate(from: $current_store_version, to: $latest_store_version);
}

if(INITIAL_RUN) seed();
