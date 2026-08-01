<?php

  if(isset($_POST['custom-css'])) {
    $css = trim($_POST['custom-css']) == "" ? null : $_POST['custom-css'];

    \store\update_config('developer.custom-css', $css);
    \store\put_audit_log('config', 'developer', "Updated custom CSS.", 'user');

    see_other("/settings/developer/custom-code");
  }

?>
<form class="custom-code-form">
  <label for="custom-css">CSS</label>
  <textarea class="custom-code-form__input" id="custom-css" name="custom-css" rows="5" spellcheck="false"
    placeholder="::placeholder {
  background: #8853d4;
  color: #ffffff;
  opacity: 0.8;
}"
    x-post="/settings/developer/custom-code/edit" x-on="blur"><?= esc_inner((string) \config\canonical_value('developer.custom-css')) ?></textarea>
</form>
