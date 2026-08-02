<?php
  $source_field = function($row) {
    $selected = @$row['calendar_id']
      ? "calendar:{$row['calendar_id']}"
      : (@$row['subscription_id'] ? "subscription:{$row['subscription_id']}" : null);
?>
  <select name="source[]" required data-value data-unique="share-source">
    <?php foreach(\shares\sources() as $option): ?>
      <option value="<?= esc_attr($option['key']) ?>" <?= $selected == $option['key'] ? 'selected' : '' ?>>
        <?= esc_inner($option['label']) ?>
      </option>
    <?php endforeach ?>
  </select>
  <select name="mode[]" required>
    <option value="full" <?= @$row['mode'] != 'redacted' ? 'selected' : '' ?>>Full</option>
    <option value="redacted" <?= @$row['mode'] == 'redacted' ? 'selected' : '' ?>>Redacted</option>
  </select>
<?php } ?>

<ul class="settings-listing settings-listing--shares">
  <?php foreach(\store\list_shares() as $share): ?>
    <li class="share-editor">
      <form x-post="/settings/shares/edit" x-on="change" x-target="#shares-listing">
        <div class="share-editor__header">
          <input name="id" type="hidden" value="<?= esc_attr($share['id']) ?>">
          <input name="name" type="text" required value="<?= esc_attr($share['name']) ?>" aria-label="Share name">
          <button type="button" x-post="/settings/shares/cycle?id=<?= esc_attr($share['id']) ?>" x-target="#shares-listing" z-confirm="Cycle this token? The current URL will stop working.">Cycle token</button>
          <button type="button" x-delete="/settings/shares/delete?id=<?= esc_attr($share['id']) ?>" x-target="#shares-listing" z-confirm="Delete this shared feed?">&times;</button>
        </div>
        <input class="share-editor__url" type="url" readonly value="<?= esc_attr(CANONICAL . "/shared/{$share['token']}.ics") ?>" aria-label="Shared feed URL">
        <?php repeat_field("Sources", "Source", \store\list_share_sources($share['id']), $source_field, confirm: "") ?>
        <div class="share-editor__toggles">
          <label><input name="birthdays" type="checkbox" value="true" <?= cast_bool($share['birthdays']) ? 'checked' : '' ?>> Birthdays</label>
          <label><input name="deadlines" type="checkbox" value="true" <?= cast_bool($share['deadlines']) ? 'checked' : '' ?>> Deadlines</label>
        </div>
      </form>
    </li>
  <?php endforeach ?>
</ul>
