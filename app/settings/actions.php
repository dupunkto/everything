<?php

  $back = @$_GET['back'];

  // Only accept 'back' parameters to these pages,
  // do not allow arbitrary redirects (that would be XSS).
  $backs = [
    "/calendar" => "Calendar",
    "/contacts" => "Contacts",
    "/todo" => "ToDo",
  ];

?>

<?php if(is_string($back) and isset($backs[$back])): ?>
  <a href="<?= esc_attr($back) ?>" class="nav__action" title="Back to <?= esc_attr($backs[$back]) ?>"><i class="fa-regular fa-arrow-left"></i></a>
<?php endif ?>
