<?php
// Common form components.

namespace forms;

function options(
  $name,
  $options,
  $selected = null,
  $flat = false,
  $reverse = false,
  $capitalize = true
) {
  if($reverse) $options = array_reverse($options, true);
  ?>
    <select name="<?= $name ?>" id="<?= $name ?>">
      <?php 
        foreach($options as $value => $label) {
          if(is_int($value) and !$flat) {
            $value = $label;
            if($capitalize) $label = ucfirst($label);
          }
      ?>
        <option value="<?= esc_attr($value) ?>" <?php if($selected == $value) echo "selected" ?>>
          <?= $label ?>
        </option>
      <?php } ?>
    </select>
  <?php
}
