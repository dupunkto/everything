<?php
$lists = [];

$query = @$_GET["query"];
$tasks = \store\list_tasks($query);
$tags = \store\list_tags();

$query_tags = [];
$include_tags = [];

preg_match_all('/\btag:(\S+)/', $query, $query_tags);

foreach($query_tags as $tag) {
  if(in_array($tag, array_column($tags, 'labels'))) $include_tags[] = $tag;
}

$root_tags = array_filter($tags, fn($tag) => !$tag['parent_id']);

foreach($tasks as $task) foreach($root_tags as $tag) {
  if(in_array($task['tags'], $tag['label'])) $lists[$tag['label']][] = $task;
}

function overlap($array_a, $array_b) {
  return count(array_intersect($array_a, $array_b));
}

?>
<?php foreach($root_tags as $tag): ?>
  <?php if(@$lists[$tag['label']] != []): ?>
    <section>
      <h3>~<?= $tag['label'] ?></h3>

      <ul>
        <?php foreach($lists[$tag['label']] as $task): ?>
          <?php if($query_tags == [] || overlap($task['tags'], $include_tags) >= 1): ?>
            <li>
              <input
                type="checkbox"
                class="checkbox"
                <?php if(in_array($task["status"], ['done', 'nvm'])) echo "checked" ?>
                <?php if($task["status"] == "nvm") echo "disabled" ?>
                x-get="/todo/listing?<?= http_build_query($_GET) ?>"
                x-target="#todo-listing"
              >
              <span class="id"><?= $task["id"] ?></span>
              <h4 class="title"><?= esc_inner($task["title"]) ?></h4>
              <?php if($task["content"]): ?>
                <p class="content"><?= esc_inner($task["content"]) ?></p>
              <?php endif; ?>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
<?php endforeach ?>
