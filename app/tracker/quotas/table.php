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
    <?php foreach($rows as $quota): ?>
      <?php
        $quota_seconds = $quota['minutes'] * 60;
        $difference = $quota['worked_seconds'] - $quota_seconds;
        $percentage = (int)round($quota['worked_seconds'] / $quota_seconds * 100);
      ?>
      <tr>
        <th><span class="quota-table__tag" style="--tag-color: <?= esc_attr($quota['color']) ?>"><?= esc_inner($quota['label']) ?></span></th>
        <td><?= \quotas\duration($quota_seconds) ?></td>
        <td><?= \quotas\duration($quota['worked_seconds']) ?></td>
        <td><?= $percentage ?>%</td>
        <td class="<?= $difference >= 0 ? 'quota-table__positive' : 'quota-table__negative' ?>"><?= $difference >= 0 ? '+' : '-' ?><?= \quotas\duration($difference) ?></td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>
