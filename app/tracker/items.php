<?php foreach($timings as $timing): ?>
  <li class="tracker-list__item" data-id="<?= esc_attr($timing['id']) ?>">
    <h3 class="tracker-list__description">
      <?= $timing['description'] ? esc_inner($timing['description']) : '<i class="empty">No description.</i>' ?>
    </h3>
    <p class="tracker-list__times">
      From
      <time datetime="<?= esc_attr($timing['starts_at']) ?>" local>
        <?= gmdate("Y-m-d H:i:s", strtotime($timing['starts_at'])) ?> (UTC)
      </time>
      to
      <time datetime="<?= esc_attr($timing['ends_at']) ?>" local>
        <?= gmdate("Y-m-d H:i:s", strtotime($timing['ends_at'])) ?> (UTC)
      </time>
    </p>
    <p class="tracker-list__duration">
      <time>
        <?php $i = date_diff(date_create($timing['starts_at']), date_create($timing['ends_at'])) ?>
        <?= (int) $i->format("%H") ?>:<?= $i->format("%I") ?>:<?= $i->format("%S") ?>
      </time>
    </p>
  </li>
<?php endforeach ?>
<?php if($has_more) infinite_scroll("/tracker/listing?page=" . ($page + 1)) ?>
