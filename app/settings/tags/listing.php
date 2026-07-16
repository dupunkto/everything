<?php

  $tags = \store\list_tags();

  $children_of = [];
  foreach($tags as $t) $children_of[$t['parent_id']][] = $t['id'];

  // A tag's own subtree (itself + all descendants) can't become its parent
  // without forming a cycle.
  $subtree_of = function($id) use (&$subtree_of, $children_of) {
    $set = [$id => true];
    foreach($children_of[$id] ?? [] as $child)
      $set += $subtree_of($child);
    return $set;
  };

?>
<ul class="settings-listing settings-listing--tags" data-reorder-url="/settings/tags/reorder">
  <?php foreach($tags as $tag): ?>
    <?php $forbidden = $subtree_of($tag['id']) ?>
    <li class="settings-listing__item" data-tag-id="<?= $tag['id'] ?>">
      <form class="settings-editor" x-post="/settings/tags/edit" x-on="change" x-target="#tags-listing">
        <span class="settings-editor__drag-handle" title="Drag to reorder"><i class="fa-solid fa-grip"></i></span>
        <input name="id" type="hidden" value="<?= $tag['id'] ?>">
        <input name="color" type="color" required value="<?= esc_attr($tag['color']) ?>">
        <input name="label" type="text" required value="<?= esc_attr($tag['label']) ?>">

        <select name="parent">
          <option value="" <?php if(!$tag['parent_id']) echo "selected" ?>>[root]</option>
          <?php foreach($tags as $parent): ?>
            <?php if(isset($forbidden[$parent['id']])) continue ?>
            <option value="<?= $parent['id'] ?>"
              <?php if($parent['id'] == $tag['parent_id']) echo "selected" ?>>
              <?= esc_inner($parent['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button type="button" x-delete="/settings/tags/delete?id=<?= $tag['id'] ?>" x-target="#tags-listing" <?php if($tag['label'] != "Untitled tag") echo 'x-confirm="Are you sure?"' ?>>&times;</button>
      </form>
    </li>
  <?php endforeach ?>
</ul>
