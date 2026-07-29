<?php foreach($notes as $note): ?>
  <?php if(NOTES_LAYOUT == 'masonry'): ?>
    <li class="note-card" tabindex="0">
      <a class="button note-card__edit" href="/notes/edit?id=<?= esc_attr($note['id']) ?>" title="Edit" z-key="enter e o">
        <i class="fa-regular fa-pen-to-square"></i>
      </a>

      <h2 class="note-card__title">
        <span class="humid"><?= esc_inner($note['id']) ?></span>
        <?= esc_inner($note['title']) ?>
      </h2>

      <?php if($note['content']): ?>
        <div class="note-card__content"><?= markdown($note['content']) ?></div>
      <?php endif ?>

      <button type="button" x-delete="/notes/delete?id=<?= esc_attr($note['id']) ?>" z-key="d" z-confirm="Delete this note?" hidden></button>
    </li>
  <?php else: ?>
    <li class="listing__item" tabindex="0">
      <h4 class="listing__title">
        <span class="humid"><?= esc_inner($note['id']) ?></span>
        <a class="listing__link" href="/notes/edit?id=<?= esc_attr($note['id']) ?>" tabindex="-1" z-key="enter e o">
          <?= esc_inner($note['title']) ?>
        </a>
      </h4>
      <button type="button" x-delete="/notes/delete?id=<?= esc_attr($note['id']) ?>" z-key="d" z-confirm="Delete this note?" hidden></button>
    </li>
  <?php endif ?>
<?php endforeach ?>
<?php if($has_more): ?>
  <?php infinite_scroll("/notes/listing?" . http_build_query(['q' => $query, 'page' => $page + 1])) ?>
<?php endif ?>
