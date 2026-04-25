<ul>
  <?php foreach(\core\list_timings() as $timing): ?>
    <li>
      <h3 class="description">
        <?= $timing['description'] ? esc_inner($timing['description']) : '<i class="empty">No description.</i>' ?>
      </h3>
      <p class="times">
        From
        <time datetime="<?= esc_attr($timing['starts_at']) ?>" local>
          <?= gmdate("Y-m-d H:i:s", strtotime($timing['starts_at'])) ?> (UTC)
        </time>
        to
        <time datetime="<?= esc_attr($timing['ends_at']) ?>" local>
          <?= gmdate("Y-m-d H:i:s", strtotime($timing['ends_at'])) ?> (UTC)
        </time>
      </p>
      <p class="duration">
        <time>
          <?php $i = date_diff(date_create($timing['starts_at']), date_create($timing['ends_at'])) ?>
          <?= $i->format("%H") ?>h<?= $i->format("%I") ?>m<?= $i->format("%S") ?>s
        </time>
      </p>
    </li>
  <?php endforeach ?>
</ul>
