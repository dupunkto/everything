<?php $tags = \store\list_tags() ?>
<ul>
  <?php foreach($tags as $tag): ?>
    <li>
      <form class="tags-editor" x-post="/settings/tags/edit" x-on="change" x-target="#tags-listing">
        <input name="id" type="hidden" value="<?= $tag['id'] ?>">
        <input name="color" type="color" value="<?= esc_attr($tag['color']) ?>">
        <input name="label" type="text" value="<?= esc_attr($tag['label']) ?>">

        <select name="parent">
          <option value="" <?php if(!$tag['parent_id']) echo "selected" ?>>None</option>
          <?php foreach($tags as $parent): ?>
            <?php if($parent['id'] == $tag['id']) continue ?>
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
