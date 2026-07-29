<?php

  $levels = ['debug', 'info', 'warn', 'error'];
  $level = in_array(@$_GET['level'], $levels) ? $_GET['level'] : 'info';
  $levels = array_slice($levels, array_search($level, $levels));

  $sources = isset($_GET['filters'])
    ? array_values(array_intersect((array)@$_GET['source'], ['audit', 'system', 'http']))
    : ['audit', 'system'];

  $parse_datetime = function($value) {
    $value = cast_string($value);
    if(!$value) return null;

    $utc = cast_datetime_utc(substr($value, 0, 10), substr($value, 11));
    return $utc ? (new \DateTimeImmutable($utc))->format('Y-m-d H:i:s') : null;
  };

  $logs = \store\list_logs_filtered(
    $sources,
    $levels,
    cast_string(@$_GET['message']),
    $parse_datetime(@$_GET['from']),
    $parse_datetime(@$_GET['to']),
    max(1, cast_int(@$_GET['limit']) ?: 1000)
  );

  $http_entity = function($uri) {
    $parts = @parse_url($uri) ?: [];
    $path = trim(rawurldecode(@$parts['path'] ?: ''), '/');
    parse_str(@$parts['query'] ?: '', $query);

    $section = explode('/', $path)[0];
    $table = match($section) {
      'addresses' => 'addresses',
      'bookmarks' => 'bookmarks',
      'calendar' => 'appointments',
      'contacts' => @$query['kind'] == 'org' ? 'organisations' : 'contacts',
      'notes' => 'notes',
      'todo' => 'tasks',
      'tracker' => 'timings',
      'wishlist' => 'wishes',
      default => $path,
    };

    $id = @$query['id'] ?: @$query['edit'] ?: '';
    return [$table, is_scalar($id) ? (string)$id : ''];
  };

  $edit_url = function($log) {
    if(!$log['record_id']) return null;
    $id = rawurlencode($log['record_id']);

    return match($log['table_name']) {
      'addresses' => "/addresses?edit=$id",
      'bookmarks' => "/bookmarks/edit?id=$id",
      'contacts' => "/contacts?kind=person&edit=$id",
      'notes' => "/notes/edit?id=$id",
      'organisations' => "/contacts?kind=org&edit=$id",
      'tasks' => "/todo/edit?id=$id",
      'timings' => "/tracker?edit=$id",
      'wishes' => "/wishlist/edit?id=$id",
      default => null,
    };
  };

?>
<?php foreach($logs as $log): ?>
  <?php if($log['source'] == 'http') [$log['table_name'], $log['record_id']] = $http_entity($log['message']) ?>
  <?php $entity = $log['table_name'] . ($log['record_id'] ? "/{$log['record_id']}" : '') ?>
  <?php $url = @$log['deleted'] ? null : $edit_url($log) ?>
  <?php $datetime = (new \DateTimeImmutable($log['changed_at'], timezone: new \DateTimeZone("UTC")))->format('c') ?>
  <tr class="logs__row--<?= esc_attr($log['level']) ?>">
    <td><?= esc_inner($log['level']) ?></td>
    <td><time datetime="<?= esc_attr($datetime) ?>" local><?= esc_inner($log['changed_at']) ?> UTC</time></td>
    <td><?= esc_inner($log['message']) ?></td>
    <td><?= esc_inner($log['operation']) ?></td>
    <td><?= esc_inner($log['author']) ?></td>
    <td>
      <?php if($url): ?>
        <a class="logs__entity" href="<?= esc_attr($url) ?>" title="<?= esc_attr($entity) ?>"><?= esc_inner($entity) ?></a>
      <?php else: ?>
        <span class="logs__entity" title="<?= esc_attr($entity) ?>"><?= esc_inner($entity) ?></span>
      <?php endif ?>
    </td>
  </tr>
<?php endforeach ?>
