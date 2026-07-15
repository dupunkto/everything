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

  $query_tags = extract_match($query, '/(?:^|\s)\+(\S+)/');
  $root_tags = array_filter($tags, fn($tag) => !$tag['parent_id']);

  foreach($tasks as $task) {
    $found = false;

    foreach($root_tags as $tag) {
      if(in_array($tag['label'], array_column($task['tags'], 'label'))) {
        $found = true;
        $lists[$tag['label']][] = $task;
        break;
      }
    }

    if(!$found) {
      $lists['all'][] = $task;
    }
  }

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
          <?php if($query_tags == [] || overlap(array_column($task['tags'], 'label'), $query_tags) >= 1): ?>
            <li class="listing__item" tabindex="0">
              <?php if($task['urgent']) circle() ?>
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
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach ?>
</div>
