<ul class="settings-listing">
  <?php foreach(\store\list_quotas() as $quota): ?>
    <li>
      <form class="settings-editor settings-editor--quota" x-post="/settings/tracker/edit" x-on="change" x-target="#quotas-listing">
        <input name="tag_id" type="hidden" value="<?= esc_attr($quota['tag_id']) ?>">
        <strong class="settings-editor__quota-tag"><?= esc_inner($quota['label']) ?></strong>
        <span class="settings-editor__quota-time">
          <input name="hours" type="number" min="0" value="<?= intdiv($quota['minutes'], 60) ?>" required aria-label="Hours"> h
          <input name="minutes" type="number" min="0" max="59" value="<?= $quota['minutes'] % 60 ?>" required aria-label="Minutes"> m
        </span>
        <select name="period" required>
          <option value="week" <?= $quota['period'] == 'week' ? 'selected' : '' ?>>weekly</option>
          <option value="month" <?= $quota['period'] == 'month' ? 'selected' : '' ?>>monthly</option>
        </select>
        <label>
          starting from:
          <input name="start_date" type="date" value="<?= esc_attr($quota['start_date']) ?>" required aria-label="Start date">
        </label>
        <button type="button" x-delete="/settings/tracker/delete?tag_id=<?= esc_attr($quota['tag_id']) ?>" x-target="#quotas-listing" x-refresh="#quota-new" title="Delete quota">&times;</button>
      </form>
    </li>
  <?php endforeach ?>
</ul>
