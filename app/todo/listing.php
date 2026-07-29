<?php

  $lists = [];

  $query = $_GET['q'] ?? $_POST['q'] ?? "";
  $pinned = json_decode(@$_GET['i'] ?: @$_POST['i'] ?: "[]", true);

  $tags = \store\list_tags();
  $tasks = \store\list_tasks($query, array_keys($pinned));
  $today = local_date("Y-m-d");
  $now = time();

  [$query_tags, $query_terms] = \core\parse_query($query, $tags);

  $is_overdue = fn($task) =>
    in_array($task['status'], ['todo', 'wip', 'blocked'])
    && $task['next']
    && (cast_boolean($task['due_all_day'])
      ? local_date("Y-m-d", $task['next']) < $today
      : strtotime($task['next']) < $now);

  $depth_of = function($tag) use ($tags) {
    $depth = 0;
    $by_id = array_column($tags, null, 'id');

    while($tag['parent_id']) {
      $tag = $by_id[$tag['parent_id']]; $depth++;
    }

    return $depth;
  };

  foreach($tasks as $task) {
    $ids = array_column($task['tags'], 'id');

    if($query_tags && array_diff($query_tags, $ids)) continue;
    if(!str_contains_terms("{$task['title']} {$task['content']}", $query_terms)) continue;

    $task['overdue'] = $is_overdue($task);

    if(TODO_DISPLAY == 'status') {
      $lists[$task['overdue'] ? 'overdue' : $task['status']][] = $task;
      continue;
    }

    // A task is placed in the column of the tag closest to root.
    // (and of those, the first in the configured order)
    $best = null;

    foreach($tags as $tag) {
      if(!in_array($tag['id'], $ids)) continue;
      if(!$best || $depth_of($tag) < $depth_of($best)) $best = $tag;
    }

    $lists[$best ? tag_slug($best['label']) : 'all'][] = $task;
  }

  $status_rank = array_flip(['wip', 'todo', 'blocked', 'backlog', 'done', 'nvm']);
  $sort_rank = fn($task) => $task['overdue'] ? 0 : $status_rank[$task['status']] + 1;

  foreach($lists as &$tasks) {
    $fixed = sorted(
      array_filter($tasks, fn($task) => isset($pinned[$task['id']])),
      fn($a, $b) => $pinned[$a['id']] <=> $pinned[$b['id']]
    );

    $tasks = sorted(
      array_filter($tasks, fn($task) => !isset($pinned[$task['id']])),
      fn($a, $b) => $sort_rank($a) <=> $sort_rank($b)
    );

    $tasks = array_reduce($fixed, fn($tasks, $task) => insert(
      $tasks,
      max(0, min($pinned[$task['id']], count($tasks))),
      $task
    ), $tasks);
  }
  unset($tasks);

  $ordered = [];
  $columns = TODO_DISPLAY == 'status'
    ? ['overdue', 'todo', 'wip', 'blocked', 'backlog', 'done', 'nvm']
    : ['all', ...array_map(fn($tag) => tag_slug($tag['label']), $tags)];

  foreach($columns as $key) {
    if(isset($lists[$key])) $ordered[$key] = $lists[$key];
  }

  $lists = $ordered;

  $expand_open = function($tokens) {
    $expanded = [];

    foreach($tokens as $token) {
      $expanded = $token == "is:open"
        ? [...$expanded, "is:todo", "is:wip", "is:blocked"]
        : [...$expanded, $token];
    }

    return array_values(array_unique($expanded));
  };

  $toggle_labels = [
    "is:blocked" => "blocked",
    "is:backlog" => "backlog",
    "is:done" => "finished",
    "is:nvm" => "shelves",
  ];
  $toggle_tokens = array_keys($toggle_labels);
  $tokens = $expand_open(str_explode($query));
  $default_tokens = $expand_open(str_explode(TODO_DEFAULT_QUERY));
  $base_tokens = array_values(array_diff($default_tokens, $toggle_tokens));
  $is_supported = array_values(array_diff($tokens, $toggle_tokens)) == $base_tokens;

  $toggle_query = function($toggle) use ($tokens, $base_tokens, $toggle_tokens) {
    $selected = array_intersect($toggle_tokens, $tokens);
    $selected = in_array($toggle, $selected)
      ? array_diff($selected, [$toggle])
      : [...$selected, $toggle];

    return join(" ", [...$base_tokens, ...array_intersect($toggle_tokens, $selected)]);
  };

  $tag_tokens = str_explode($query);
  $tag_default_tokens = str_explode(TODO_DEFAULT_QUERY);
  $unfinished_tokens = array_values(array_diff($tag_default_tokens, ["is:done"]));
  $finished_tokens = [...$unfinished_tokens, "is:done"];
  $is_default = $tag_tokens == $unfinished_tokens || $tag_tokens == $finished_tokens;
  $is_finished = in_array("is:done", $tag_tokens);
  $views = ["is:todo" => "ToDo", "is:nvm" => "Shelves", "is:backlog" => "Backlog"];
  $view_token = "is:todo";
  $view = "ToDo";

  foreach($tag_tokens as $token) {
    if(!isset($views[$token])) continue;
    $view_token = $token;
    $view = $views[$token]; break;
  }

?>
<h1 class="page-header__title"><strong><?= TODO_DISPLAY == 'tag' ? $view : "ToDo" ?></strong></h1>

<nav class="view-nav">
  <?php if(TODO_DISPLAY == 'tag'): ?>
    <?php if($view == "ToDo"): ?>
      <?php if(!$is_default): ?>
        <button type="button" z-set="#todo-search" value="<?= esc_attr(TODO_DEFAULT_QUERY) ?>">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
      <?php elseif($is_finished): ?>
        <button type="button" z-set="#todo-search" value="<?= esc_attr(join(" ", array_diff($tag_tokens, ["is:done"]))) ?>">
          <i class="fa-regular fa-eye"></i> Hide finished
        </button>
      <?php else: ?>
        <button type="button" z-set="#todo-search" value="<?= esc_attr(join(" ", [...$tag_tokens, "is:done"])) ?>">
          <i class="fa-regular fa-eye-slash"></i> Show finished
        </button>
      <?php endif ?>
      <button type="button" z-set="#todo-search" value="is:nvm"><i class="fa-regular fa-box-archive"></i> Shelves</button>
      <button type="button" z-set="#todo-search" value="is:backlog"><i class="fa-regular fa-folder-open"></i> Backlog</button>
    <?php else: ?>
      <?php if($tag_tokens != [$view_token]): ?>
        <button type="button" z-set="#todo-search" value="<?= esc_attr($view_token) ?>">
          <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
      <?php endif ?>
      <button type="button" z-set="#todo-search" value="<?= esc_attr(TODO_DEFAULT_QUERY) ?>">&larr; Back to todo</button>
    <?php endif ?>
  <?php elseif(!$is_supported): ?>
    <button type="button" z-set="#todo-search" value="<?= esc_attr(TODO_DEFAULT_QUERY) ?>">
      <i class="fa-solid fa-rotate-left"></i> Reset
    </button>
  <?php else: ?>
    <?php foreach($toggle_labels as $token => $label): ?>
      <?php $shown = in_array($token, $tokens) ?>
      <button type="button" z-set="#todo-search" value="<?= esc_attr($toggle_query($token)) ?>">
        <i class="fa-regular fa-eye<?= $shown ? "" : "-slash" ?>"></i>
        <?= esc_inner($label) ?>
      </button>
    <?php endforeach ?>
  <?php endif ?>
</nav>

<div class="listing listing--<?= TODO_LAYOUT == 'horizontal' ? 'horizontal' : 'masonry' ?>">
  <?php foreach($lists as $list => $tasks): ?>
    <section>
      <h3 class="listing__heading"><?= TODO_DISPLAY == 'tag' ? "~" : "" ?><?= esc_inner($list) ?></h3>

      <ul>
        <?php foreach($tasks as $position => $task): ?>
          <?php $state = json_encode(array_replace($pinned, [$task['id'] => $position])) ?>
          <li class="listing__item<?= $task['overdue'] ? " todo__item--overdue" : "" ?>" tabindex="0">
            <?php if(cast_boolean($task['urgent'])) circle() ?>
            <form x-post="/todo/urgent" x-target="#todo-listing" x-on="change" hidden>
              <input type="hidden" name="id" value="<?= $task['id'] ?>">
              <input type="hidden" name="urgent" value="false">
              <input type="hidden" name="q" value="<?= esc_attr($query) ?>">
              <input type="hidden" name="i" value="<?= esc_attr($state) ?>">
              <input type="checkbox" name="urgent" value="true" z-key="m" <?php if(cast_boolean($task['urgent'])) echo "checked" ?> hidden>
            </form>
            <form x-post="/todo/status" x-target="#todo-listing" x-on="change">
              <input type="hidden" name="id" value="<?= $task['id'] ?>">
              <input type="hidden" name="status" value="todo" />
              <input type="hidden" name="comment" value="" />
              <input type="hidden" name="q" value="<?= esc_attr($query) ?>" />
              <input type="hidden" name="i" value="<?= esc_attr($state) ?>" />

              <input
                type="checkbox"
                class="listing__check"
                name="status"
                value="done"
                z-key="c"
                <?php if(in_array($task['status'], ['done', 'nvm'])) echo "checked" ?>
                <?php if($task['status'] == "nvm") echo "disabled" ?>
              >

              <?php $status_for = fn($task, $target) => $task['status'] == $target ? "todo" : $target ?>

              <button name="status" value="<?= $status_for($task, 'backlog') ?>" z-key="b" hidden></button>
              <button name="status" value="<?= $status_for($task, 'wip') ?>" z-key="w" hidden></button>
              <button name="status" value="<?= $status_for($task, 'blocked') ?>" z-key="x" hidden></button>
              <button name="status" value="todo" z-key="u" hidden></button>
              <button name="status" value="<?= $status_for($task, 'nvm') ?>" z-key="s" hidden></button>
            </form>
            <h4 class="listing__title">
              <span class="humid"><?= $task['id'] ?></span>
              <a class="listing__link" href="/todo/edit?id=<?= $task['id'] ?>" tabindex="-1" z-key="enter e o">
                <?= esc_inner($task['title']) ?>
              </a>
            </h4>
            <?php if(in_array($task['status'], ['wip', 'blocked']) || $task['recurrence']): ?>
              <span class="todo__badges">
                <?php if(in_array($task['status'], ['wip', 'blocked'])): ?>
                  <span class="todo__badge todo__badge--<?= esc_attr($task['status']) ?>"><?= esc_inner($task['status']) ?></span>
                <?php endif ?>
                <?php if($task['recurrence']): ?><span class="todo__badge">recurring</span><?php endif ?>
              </span>
            <?php endif ?>
            <button type="button" x-delete="/todo/delete?id=<?= $task['id'] ?>" z-key="d" z-confirm="Delete this task?" hidden></button>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach ?>
</div>
