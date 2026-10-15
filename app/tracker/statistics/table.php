<?php if(!$rows): ?>
  <p class="placeholder">Nothing tracked</p>
<?php else: ?>
<table class="statistics-table">
  <thead>
    <tr>
      <th></th>
      <th>Target</th>
      <th>Tracked</th>
      <th>%</th>
      <th>Balance</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($rows as $row): ?>
      <tr>
        <th><span class="statistics-table__tag" style="--tag-color: <?= esc_attr($row['color']) ?>"><?= esc_inner($row['label']) ?></span></th>
        <?php if(isset($row['minutes'])): ?>
          <?php
            $quota_seconds = $row['minutes'] * 60;
            $difference = $row['worked_seconds'] - $quota_seconds;
            $percentage = (int)round($row['worked_seconds'] / $quota_seconds * 100);
          ?>
          <td><?= \statistics\duration($quota_seconds) ?></td>
          <td><?= \statistics\duration($row['worked_seconds']) ?></td>
          <td><?= $percentage ?>%</td>
          <td class="<?= $difference >= 0 ? 'statistics-table__positive' : 'statistics-table__negative' ?>"><?= $difference >= 0 ? '+' : '-' ?><?= \statistics\duration($difference) ?></td>
        <?php else: ?>
          <td></td>
          <td><?= \statistics\duration($row['worked_seconds']) ?></td>
          <td></td>
          <td></td>
        <?php endif ?>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>
