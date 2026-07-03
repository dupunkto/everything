<?php

$lists = [];

$query = @$_GET['q'] ?? @$_POST['q'];
$include = @$_GET['i'] ?? @$_POST['i'];

// This array includes IDs of items that have just been clicked.
// We do not want to have them disappear from under the users cursor,
// that is a very bad UX. So this 'skips' them from the query that
// is currently active.
$include = $include ? explode(",", $include) : [];

$tasks = \store\list_tasks($query, $include);
$tags = \store\list_tags();

$query_tags = extract_match($query, '/\btag:(\S+)/');
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

?>
<?php foreach($lists as $list => $tasks): ?>
  <section>
    <h3>~<?= $list ?></h3>

    <?php $tag = find_by($tags, 'label', $list) ?>

    <ul>
      <?php foreach($tasks as $task): ?>
        <?php if($query_tags == [] || overlap(array_column($task['tags'], 'label'), $query_tags) >= 1): ?>
          <li>
            <form x-post="/todo/status" x-target="#todo-listing" x-on="change">
              <input type="hidden" name="id" value="<?= $task['id'] ?>">
              <input type="hidden" name="status" value="todo" />
              <input type="hidden" name="q" value="<?= esc_attr($query) ?>" />
              <input type="hidden" name="i" value="<?= esc_attr(join(",", array_unique([...$include, $task['id']]))) ?>" />
              <input
                type="checkbox"
                class="checkbox"
                name="status"
                value="done"
                <?php if(in_array($task['status'], ['done', 'nvm'])) echo "checked" ?>
                <?php if($task['status'] == "nvm") echo "disabled" ?>
              >
            </form>
            <h4 class="title"><span class="humid"><?= $task['id'] ?></span> <?= esc_inner($task['title']) ?> </h4>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach ?>
