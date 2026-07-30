<?php foreach($timings as $timing): ?>
  <li class="tracker-list__item" data-id="<?= esc_attr($timing['id']) ?>">
    <div class="tracker-list__meta">
      <h3 class="tracker-list__description">
        <?= $timing['description'] ? esc_inner($timing['description']) : '<i class="empty">No description.</i>' ?>
      </h3>
      <?php foreach(@$timing_tags[$timing['id']]['items'] ?: [] as $tag): ?>
        <span class="tag" style="--tag-color: <?= esc_attr($tag['color']) ?>"><?= esc_inner($tag['label']) ?></span>
      <?php endforeach ?>
    </div>
    <p class="tracker-list__duration">
      <span class="tracker-list__range">
        <time datetime="<?= esc_attr($timing['starts_at']) ?>" local>
          <?= gmdate("Y-m-d H:i:s", strtotime($timing['starts_at'])) ?> (UTC)
        </time>
        &ndash;
        <time datetime="<?= esc_attr($timing['ends_at']) ?>" local="time-if-same-day">
          <?= gmdate("Y-m-d H:i:s", strtotime($timing['ends_at'])) ?> (UTC)
        </time>
      </span>
      <time class="tracker-list__total">
        <?php $i = date_diff(date_create($timing['starts_at']), date_create($timing['ends_at'])) ?>
        <?= (int) $i->format("%H") ?>:<?= $i->format("%I") ?>:<?= $i->format("%S") ?>
      </time>
    </p>
  </li>
<?php endforeach ?>
<?php if($has_more) infinite_scroll("/tracker/listing?page=" . ($page + 1)) ?>
