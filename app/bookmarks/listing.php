<?php

  $bookmarks = \store\list_bookmarks($_GET['q'] ?? $_POST['q'] ?? "");

?>
<ul class="listing">
  <?php foreach($bookmarks as $bookmark): ?>
    <?php $favicon = $bookmark['favicon'] ?: \bookmarks\fallback_favicon($bookmark['url']) ?>
    <li class="listing__item bookmark" tabindex="0">
      <a class="bookmark__edit" href="/bookmarks/edit?id=<?= esc_attr($bookmark['id']) ?>" tabindex="-1" aria-label="Edit bookmark"></a>
      <?php if($favicon): ?>
        <img class="bookmark__favicon" src="<?= esc_attr($favicon) ?>" alt="" onerror="this.replaceWith(Object.assign(document.createElement('i'), {className: 'bookmark__favicon fa-regular fa-globe'}))">
      <?php else: ?>
        <i class="bookmark__favicon fa-regular fa-globe"></i>
      <?php endif ?>
      <h4 class="listing__title bookmark__title">
        <?php if($bookmark['label']): ?>
          <span><?= esc_inner($bookmark['label']) ?></span>
        <?php endif ?>
        <a class="bookmark__link" href="<?= esc_attr($bookmark['url']) ?>">
          <?= esc_inner($bookmark['url']) ?>
        </a>
      </h4>

      <a href="/bookmarks/edit?id=<?= esc_attr($bookmark['id']) ?>" z-key="enter e o" hidden></a>
      <a href="<?= esc_attr($bookmark['url']) ?>" z-key="g" hidden></a>
      <button type="button" x-delete="/bookmarks/delete?id=<?= esc_attr($bookmark['id']) ?>" z-key="d" z-confirm="Delete this bookmark?" hidden></button>
    </li>
  <?php endforeach ?>
</ul>
