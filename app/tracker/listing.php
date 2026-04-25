<?php foreach(\core\list_timings() as $timing): ?>
  <h3><?= esc_inner($timing['description']) ?></h3>
  <p>
    <time datetime="<?= esc_attr($timing['starts_at']) ?>" local>
      <?= date("Y-m-d H:I:s", strtotime($timing['starts_at'])) ?>
    </time>
    &mdash;
    <time datetime="<?= esc_attr($timing['ends_at']) ?>" local>
      <?= date("Y-m-d H:I:s", strtotime($timing['ends_at'])) ?>
    </time>
  </p>
<?php endforeach ?>