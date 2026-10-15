<?php if(!$rows): ?>
  <p class="placeholder">Nothing tracked</p>
<?php else: ?>
<table class="quota-table">
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
        <th><span class="quota-table__tag" style="--tag-color: <?= esc_attr($row['color']) ?>"><?= esc_inner($row['label']) ?></span></th>
        <?php if(isset($row['minutes'])): ?>
          <?php
            $quota_seconds = $row['minutes'] * 60;
            $difference = $row['worked_seconds'] - $quota_seconds;
            $percentage = (int)round($row['worked_seconds'] / $quota_seconds * 100);
          ?>
          <td><?= \quotas\duration($quota_seconds) ?></td>
          <td><?= \quotas\duration($row['worked_seconds']) ?></td>
          <td><?= $percentage ?>%</td>
          <td class="<?= $difference >= 0 ? 'quota-table__positive' : 'quota-table__negative' ?>"><?= $difference >= 0 ? '+' : '-' ?><?= \quotas\duration($difference) ?></td>
        <?php else: ?>
          <td></td>
          <td><?= \quotas\duration($row['worked_seconds']) ?></td>
          <td></td>
          <td></td>
        <?php endif ?>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>
