<?php

  $notes = \store\list_notes($_GET['q'] ?? $_POST['q'] ?? "");

?>
<ul class="notes-grid">
  <?php foreach($notes as $note): ?>
    <li class="note-card" tabindex="0">
      <a class="button note-card__edit" href="/notes/edit?id=<?= esc_attr($note['id']) ?>" title="Edit" z-key="enter e o">
        <i class="fa-regular fa-pen-to-square"></i>
      </a>

      <h2 class="note-card__title">
        <span class="humid"><?= esc_inner($note['id']) ?></span>
        <?= esc_inner($note['title']) ?>
      </h2>

      <?php if($note['content']): ?>
        <p class="note-card__content"><?= esc_inner($note['content']) ?></p>
      <?php endif ?>

      <a href="/notes/delete?id=<?= esc_attr($note['id']) ?>" z-key="d" z-confirm="Delete this note?" hidden></a>
    </li>
  <?php endforeach ?>
</ul>
