<?php

  $lists = [];

  $query = $_GET['q'] ?? $_POST['q'] ?? "";
  $include = @$_GET['i'] ?: @$_POST['i'];

  // This array includes IDs of items that have just been clicked.
  // We do not want to have them disappear from under the users cursor,
  // that is a very bad UX. So this 'skips' them from the query that
  // is currently active.
  $include = $include ? explode(",", $include) : [];

  $tasks = \store\list_tasks($query, $include);
  $tags = \store\list_tags();

  $query_tags = array_map('tag_slug', extract_match($query, '/(?:^|\s)\+(\S+)/'));

  $depth_of = function($tag) use ($tags) {
    $depth = 0;
    $by_id = array_column($tags, null, 'id');

    while($tag['parent_id']) {
      $tag = $by_id[$tag['parent_id']]; $depth++;
    }

    return $depth;
  };

  foreach($tasks as $task) {
    $task_tags = array_map('tag_slug', array_column($task['tags'], 'label'));
    if($query_tags && !overlap($task_tags, $query_tags)) continue;

    // A task lands in the column of its tag closest to the root, and of those,
    // the first in the configured order.
    $ids = array_column($task['tags'], 'id');
    $best = null;

    foreach($tags as $tag) {
      if(!in_array($tag['id'], $ids)) continue;
      if(!$best || $depth_of($tag) < $depth_of($best)) $best = $tag;
    }

    $lists[$best ? tag_slug($best['label']) : 'all'][] = $task;
  }

  // Columns follow the configured order, with ~all always leading.
  $ordered = [];
  foreach(['all', ...array_map(fn($tag) => tag_slug($tag['label']), $tags)] as $key) {
    if(isset($lists[$key])) $ordered[$key] = $lists[$key];
  }
  $lists = $ordered;

  function overlap($array_a, $array_b) {
    return count(array_intersect($array_a, $array_b));
  }

  $tokens = str_explode($query);
  $finished = in_array("is:done", $tokens);

  $view = match(true) {
    in_array("is:nvm", $tokens) => "Shelves",
    in_array("is:backlog", $tokens) => "Backlog",
    default => "ToDo",
  };

  // The status a shortcut moves a task to; pressing it again reverts.
  $status_for = fn($task, $target) => $task['status'] == $target ? "todo" : $target;

?>
<h1 class="page-header__title"><strong><?= $view ?></strong></h1>

<nav class="view-nav">
  <?php if($view == "ToDo"): ?>
    <?php if($finished): ?>
      <button type="button" z-set="#todo-search" value="<?= esc_attr(join(" ", array_diff($tokens, ["is:done"]))) ?>">
        <i class="fa-regular fa-eye"></i> Hide finished
      </button>
    <?php else: ?>
      <button type="button" z-set="#todo-search" value="<?= esc_attr(join(" ", [...$tokens, "is:done"])) ?>">
        <i class="fa-regular fa-eye-slash"></i> Show finished
      </button>
    <?php endif ?>
    <button type="button" z-set="#todo-search" value="is:nvm"><i class="fa-regular fa-box-archive"></i> Shelves</button>
    <button type="button" z-set="#todo-search" value="is:backlog"><i class="fa-regular fa-folder-open"></i> Backlog</button>
  <?php else: ?>
    <button type="button" z-set="#todo-search" value="is:todo">&larr; Back to todo</button>
  <?php endif ?>
</nav>

<div class="listing listing--masonry">
  <?php foreach($lists as $list => $tasks): ?>
    <section>
      <h3 class="listing__heading">~<?= $list ?></h3>

      <ul>
        <?php foreach($tasks as $task): ?>
          <li class="listing__item" tabindex="0">
            <?php if(cast_boolean($task['urgent'])) circle() ?>
            <form x-post="/todo/status" x-target="#todo-listing" x-on="change">
              <input type="hidden" name="id" value="<?= $task['id'] ?>">
              <input type="hidden" name="status" value="todo" />
              <input type="hidden" name="q" value="<?= esc_attr($query) ?>" />
              <input type="hidden" name="i" value="<?= esc_attr(join(",", array_unique([...$include, $task['id']]))) ?>" />

              <input
                type="checkbox"
                class="listing__check"
                name="status"
                value="done"
                z-key="c"
                <?php if(in_array($task['status'], ['done', 'nvm'])) echo "checked" ?>
                <?php if($task['status'] == "nvm") echo "disabled" ?>
              >

              <button name="status" value="<?= $status_for($task, 'backlog') ?>" z-key="b" hidden></button>
              <button name="status" value="todo" z-key="u" hidden></button>
              <button name="status" value="<?= $status_for($task, 'nvm') ?>" z-key="s" hidden></button>
            </form>
            <h4 class="listing__title">
              <span class="humid"><?= $task['id'] ?></span>
              <a class="listing__link" href="/todo/edit?id=<?= $task['id'] ?>" tabindex="-1" z-key="enter e o">
                <?= esc_inner($task['title']) ?>
              </a>
            </h4>
            <a href="/todo/delete?id=<?= $task['id'] ?>" z-key="d" z-confirm="Delete this task?" hidden></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach ?>
</div>
