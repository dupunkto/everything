<?php

  function logs_parse_datetime($value) {
    $value = cast_str($value);
    if(!$value) return null;

    $utc = cast_dt_utc(substr($value, 0, 10), substr($value, 11));
    return $utc ? (new \DateTimeImmutable($utc))->format('Y-m-d H:i:s') : null;
  }

  function logs_http_entity($uri) {
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
      default => '',
    };

    $id = @$query['id'] ?: @$query['edit'] ?: '';
    return [$table, is_scalar($id) ? (string)$id : ''];
  }

  function logs_format_context($context) {
    $pairs = [];
    foreach($context as $key => $value) {
      if(is_bool($value)) $value = $value ? 'true' : 'false';
      elseif($value === null) $value = 'null';
      elseif(!is_scalar($value)) $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      $pairs[] = "$key=$value";
    }
    return join(" ", $pairs);
  }

  function logs_edit_url($log) {
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
  }

  $levels = ['debug', 'info', 'warn', 'error'];
  $level = in_array(@$_GET['level'], $levels) ? $_GET['level'] : 'info';
  $levels = array_slice($levels, array_search($level, $levels));

  $sources = isset($_GET['filters'])
    ? array_values(array_intersect((array)@$_GET['source'], ['audit', 'system', 'http']))
    : ['audit', 'system'];

  $logs = \store\list_logs_filtered(
    $sources,
    $levels,
    cast_str(@$_GET['message']),
    logs_parse_datetime(@$_GET['from']),
    logs_parse_datetime(@$_GET['to']),
    max(1, cast_num(@$_GET['limit']) ?: 1000)
  );

?>
<?php foreach($logs as $log): ?>
  <?php $context = $log['source'] == 'system' ? json_decode(cast_str($log['system_context']), true) : [] ?>
  <?php if(!is_array($context)) $context = [] ?>
  <?php if($log['source'] == 'http') [$log['table_name'], $log['record_id']] = logs_http_entity($log['message']) ?>
  <?php $message = $log['message'] ?>
  <?php if($log['source'] == 'system'): ?>
    <?php $log['operation'] = is_scalar(@$context['method']) ? (string)$context['method'] : '' ?>
    <?php $message = is_scalar(@$context['message']) ? (string)$context['message'] : $message ?>
    <?php $uri = is_scalar(@$context['uri']) ? (string)$context['uri'] : '' ?>
    <?php [$log['table_name'], $log['record_id']] = logs_http_entity($uri) ?>
    <?php $entity = $log['table_name'] . ($log['record_id'] ? "/{$log['record_id']}" : '') ?>
    <?php foreach(['method', 'error', 'message', 'uri'] as $key) unset($context[$key]) ?>
  <?php else: ?>
    <?php $entity = $log['table_name'] . ($log['record_id'] ? "/{$log['record_id']}" : '') ?>
  <?php endif ?>
  <?php $not_found = $log['source'] == 'http'
    ? $log['http_status'] == 404
    : ($log['source'] == 'system' && @$context['status'] == 404) ?>
  <?php $url = @$log['deleted'] || $not_found ? null : logs_edit_url($log) ?>
  <?php $message_url = $log['message'] ?>
  <?php $context = logs_format_context($context) ?>
  <?php $datetime = (new \DateTimeImmutable($log['changed_at'], timezone: new \DateTimeZone("UTC")))->format('c') ?>
  <tr class="logs__row--<?= esc_attr($log['level']) ?>">
    <td><?= esc_inner($log['level']) ?></td>
    <td><time datetime="<?= esc_attr($datetime) ?>" local><?= esc_inner($log['changed_at']) ?> UTC</time></td>
    <td<?php if(in_array($log['source'], ['http', 'system'])) echo ' class="logs__message--compact"' ?>>
      <?php if($log['source'] == 'http'): ?>
        HTTP <?= esc_inner($log['http_status']) ?> <span title="<?= esc_attr($message_url) ?>"><?= esc_inner($message_url) ?></span>
      <?php elseif($log['source'] == 'system'): ?>
        <?php $full_message = trim($message . " " . $context) ?>
        <span title="<?= esc_attr($full_message) ?>"><?= esc_inner($full_message) ?></span>
      <?php else: ?>
        <?= esc_inner($log['message']) ?>
      <?php endif ?>
    </td>
    <td><?= esc_inner($log['operation']) ?></td>
    <td><?php if(in_array($log['source'], ['audit', 'http'])) echo esc_inner($log['author']) ?></td>
    <td>
      <?php if($url): ?>
        <a class="logs__entity" href="<?= esc_attr($url) ?>" title="<?= esc_attr($entity) ?>"><?= esc_inner($entity) ?></a>
      <?php else: ?>
        <span class="logs__entity" title="<?= esc_attr($entity) ?>"><?= esc_inner($entity) ?></span>
      <?php endif ?>
    </td>
  </tr>
<?php endforeach ?>
