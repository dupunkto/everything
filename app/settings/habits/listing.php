<?php

  define('PRESET', [
    'fa-circle-check',
    'fa-dumbbell',
    'fa-book',
    'fa-glass-water',
    'fa-bed',
    'fa-broom',
    'fa-person-running',
    'fa-utensils',
    'fa-pills',
    'fa-leaf',
    'fa-music',
    'fa-brain'
  ]);

?>
<ul class="settings-listing">
  <?php foreach(\store\list_habits() as $habit): ?>
    <?php
      $icon_id = "habit-icon-" . $habit['id'];
      $icon_color = contrast_color(
        $habit['color'],
        lighten($habit['color'], 0.85),
        darken($habit['color'], 0.65)
      );
      $icon_style = "background-color: " . esc_attr($habit['color']) . "; color: " . esc_attr($icon_color);
    ?>
    <li>
      <form class="settings-editor settings-editor--habit" x-post="/settings/habits/edit" x-on="change" x-target="#habits-listing">
        <div class="settings-editor__row">
          <input name="id" type="hidden" value="<?= esc_attr($habit['id']) ?>">
          <input id="<?= esc_attr($icon_id) ?>" name="icon" type="hidden" value="<?= esc_attr($habit['icon']) ?>">
          <button type="button" class="settings-editor__icon-button" style="<?= $icon_style ?>" z-toggle="#<?= esc_attr($icon_id) ?>-picker">
            <i class="fa-solid <?= esc_attr($habit['icon']) ?>"></i>
          </button>
          <input name="color" type="color" required value="<?= esc_attr($habit['color']) ?>">
          <input class="settings-editor__title" name="title" type="text" required value="<?= esc_attr($habit['title']) ?>" placeholder="Title">
          <p class="settings-editor__phrase">
            every
            <input name="every" type="number" min="1" required value="<?= esc_attr($habit['every']) ?>">
            days
          </p>
          <button type="button" x-delete="/settings/habits/delete?id=<?= esc_attr($habit['id']) ?>" x-target="#habits-listing" z-confirm="Delete this habit and its log?">&times;</button>
        </div>

        <div id="<?= esc_attr($icon_id) ?>-picker" class="settings-editor__icon-picker" hidden>
          <span class="settings-editor__icon-picker-label">Pick one:</span>
          <div class="settings-editor__icon-presets">
            <?php foreach(PRESET as $icon): ?>
              <button type="button" style="<?= $icon_style ?>" z-set="#<?= esc_attr($icon_id) ?>" value="<?= esc_attr($icon) ?>">
                <i class="fa-solid <?= esc_attr($icon) ?>"></i>
              </button>
            <?php endforeach ?>
          </div>

          <label class="settings-editor__icon-picker-label" for="<?= esc_attr($icon_id) ?>-custom">Or type away:</label>
          <input id="<?= esc_attr($icon_id) ?>-custom" name="custom_icon" type="text" value="<?= in_array($habit['icon'], PRESET) ? '' : esc_attr($habit['icon']) ?>" placeholder="fa-circle-check">
        </div>
      </form>
    </li>
  <?php endforeach ?>
</ul>
