<?php

  $query = cast_str(@$_GET['q'] ?: @$_POST['q']) ?? "";

  if(isset($_POST['todo'], $_POST['status'])) {
    $task = \store\get_task($_POST['todo']) or fail("Task not found.", status: 404);
    $fields = \core\diff($task, status: $_POST['status'], comment: null);

    \store\set_task_status($_POST['todo'], $_POST['status']);
    \store\put_audit_log('tasks', $_POST['todo'],
      "Updated [" . join(", ", $fields) . "] for tasks/{$_POST['todo']}.", 'user');
    \caldav\mark_resource_changed('task', $_POST['todo']);
  }

  if(trim($query) == "") exit;

  $items = array_slice(\store\search($query), 0, LISTING_PAGE_SIZE);

  $range = function($item) {
    $start = new DateTime(cast_dt_local($item['starts_at']));
    $end = new DateTime(cast_dt_local($item['ends_at']));

    if($item['type'] == 'appointment' && cast_bool($item['all_day'])) {
      $end->modify('-1 second');
      return $start->format('D j M') . ($start->format('Y-m-d') != $end->format('Y-m-d')
        ? '–' . $end->format('D j M') : '');
    }

    return $start->format('D j M H:i') . '–' .
      ($start->format('Y-m-d') == $end->format('Y-m-d') ? $end->format('H:i') : $end->format('D j M H:i'));
  };

  $status_colors = ['todo' => 'blue', 'wip' => 'yellow', 'blocked' => 'red', 'backlog' => 'purple', 'done' => 'green', 'nvm' => 'gray'];

  $icons = [
    'note' => 'fa-regular fa-notebook',
    'wish' => 'fa-regular fa-book-heart',
    'appointment' => 'fa-regular fa-calendar',
    'timing' => 'fa-regular fa-timer',
    'contact' => 'fa-regular fa-user',
    'organisation' => 'fa-regular fa-building',
    'address' => 'fa-regular fa-location-dot',
  ];

  $href = fn($item) => match($item['type']) {
    'note' => "/notes/edit?id=" . rawurlencode($item['id']),
    'todo' => "/todo/edit?id=" . rawurlencode($item['id']),
    'wish' => "/wishlist/edit?id=" . rawurlencode($item['id']),
    'appointment' => "/calendar?edit=" . rawurlencode($item['id']),
    'timing' => "/tracker?edit=" . rawurlencode($item['id']),
    'bookmark' => "/bookmarks/edit?id=" . rawurlencode($item['id']),
    'contact' => "/contacts?kind=person&view=" . rawurlencode($item['id']),
    'organisation' => "/contacts?kind=org&view=" . rawurlencode($item['id']),
    'address' => "/addresses?edit=" . rawurlencode($item['id']),
  };

?>
<ul class="listing global-search__listing">
  <?php foreach($items as $item): ?>
    <?php $tags = \store\list_anything_tags($item) ?>
    <li class="listing__item global-search__item" tabindex="0">
      <?php if($item['type'] == 'todo'): ?>
        <form x-post="/search/listing" x-target="#global-search-results" x-on="change">
          <input type="hidden" name="q" value="<?= esc_attr($query) ?>">
          <input type="hidden" name="todo" value="<?= esc_attr($item['id']) ?>">
          <input type="hidden" name="status" value="<?= $item['status'] == 'done' ? 'todo' : 'done' ?>">
          <input class="listing__check" type="checkbox" name="status" value="done" <?= in_array($item['status'], ['done', 'nvm']) ? 'checked' : '' ?> <?= $item['status'] == 'nvm' ? 'disabled' : '' ?>>
        </form>
      <?php elseif($item['type'] == 'bookmark'): ?>
        <?php $favicon = $item['favicon'] ?: \bookmarks\fallback_favicon($item['url']) ?>
        <?php if($favicon): ?>
          <img class="global-search__icon" src="<?= esc_attr($favicon) ?>" alt="">
        <?php else: ?>
          <i class="global-search__icon fa-regular fa-globe"></i>
        <?php endif ?>
      <?php else: ?>
        <i class="global-search__icon <?= esc_attr($icons[$item['type']]) ?>"></i>
      <?php endif ?>

      <h4 class="listing__title global-search__title">
        <span class="humid"><?= esc_inner($item['humid']) ?></span>
        <a class="listing__link" href="<?= esc_attr($href($item)) ?>" tabindex="-1" z-key="enter">
          <?= esc_inner($item['title']) ?>
        </a>
      </h4>

      <?php if(@$item['extra']): ?>
        <span class="global-search__extra"><?= esc_inner($item['extra']) ?></span>
      <?php elseif($item['type'] == 'appointment' && $item['location']): ?>
        <span class="global-search__extra"><?= esc_inner($item['location']) ?></span>
      <?php endif ?>

      <span class="global-search__badges">
        <?php foreach($tags as $tag): ?>
          <span class="tag" style="--tag-color: <?= esc_attr($tag['color']) ?>"><?= esc_inner($tag['label']) ?></span>
        <?php endforeach ?>

        <?php if($item['type'] == 'appointment'): ?>
          <?php if($item['meeting']): ?><span class="badge" title="Meeting"><i class="fa-solid fa-video"></i></span><?php endif ?>
          <?php if($item['recurrence']): ?><span class="badge" title="Recurring"><i class="fa-solid fa-repeat"></i></span><?php endif ?>
          <?php if(cast_bool($item['urgent'])): ?><span class="badge" title="Circled"><i class="fa-regular fa-flag"></i></span><?php endif ?>
          <?php if(!cast_bool($item['going'])): ?><span class="badge" title="Declined"><i class="fa-solid fa-ban"></i></span><?php endif ?>
          <span class="badge"><?= esc_inner($range($item)) ?></span>
        <?php elseif($item['type'] == 'timing'): ?>
          <span class="badge"><?= esc_inner($range($item)) ?></span>
        <?php elseif($item['type'] == 'wish' && $item['total_price'] !== null): ?>
          <span class="badge"><?= esc_inner(format_price($item['total_price'])) ?></span>
        <?php elseif($item['type'] == 'todo'): ?>
          <span class="badge badge--<?= esc_attr($status_colors[$item['status']]) ?>"><?= esc_inner($item['status']) ?></span>
        <?php elseif($item['type'] == 'contact'): ?>
          <?php if($item['birth_day'] && $item['birth_month']): ?>
            <?php $birthday = DateTime::createFromFormat('!m-d', "{$item['birth_month']}-{$item['birth_day']}") ?>
            <span class="badge"><?= esc_inner($birthday->format('M j')) ?></span>
            <span class="badge"><?= esc_inner(star_sign($item['birth_month'], $item['birth_day'])) ?></span>
          <?php endif ?>
          <?php if($item['timezone'] && $item['timezone'] != TIMEZONE): ?>
            <span class="badge"><?= esc_inner(local_date('H:i', 'now', $item['timezone'])) ?></span>
          <?php endif ?>
        <?php endif ?>

        <span class="badge global-search__type"><?= esc_inner($item['type']) ?></span>
      </span>
    </li>
  <?php endforeach ?>
</ul>
<?php if(!$items): ?><p class="empty global-search__empty">Nothing found.</p><?php endif ?>
