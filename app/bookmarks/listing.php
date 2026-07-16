<?php

  $bookmarks = \store\list_bookmarks($_GET['q'] ?? $_POST['q'] ?? "");

?>
<ul class="listing">
  <?php foreach($bookmarks as $bookmark): ?>
    <li class="listing__item bookmark" tabindex="0">
      <a class="bookmark__edit" href="/bookmarks/edit?id=<?= esc_attr($bookmark['id']) ?>" tabindex="-1" aria-label="Edit bookmark"></a>
      <h4 class="listing__title bookmark__title">
        <span class="humid"><?= esc_inner($bookmark['id']) ?></span>
        <?php if($bookmark['label']): ?>
          <span><?= esc_inner($bookmark['label']) ?></span>
        <?php endif ?>
        <a class="bookmark__link" href="<?= esc_attr($bookmark['url']) ?>">
          <?= esc_inner($bookmark['url']) ?>
        </a>
      </h4>

      <a href="/bookmarks/edit?id=<?= esc_attr($bookmark['id']) ?>" z-key="enter e" hidden></a>
      <a href="<?= esc_attr($bookmark['url']) ?>" z-key="g" hidden></a>
      <a href="/bookmarks/delete?id=<?= esc_attr($bookmark['id']) ?>" z-key="d" z-confirm="Delete this bookmark?" hidden></a>
    </li>
  <?php endforeach ?>
</ul>
