<?php
$status = error_status($error);
$title = match($status) {
  401 => "please dont :|",
  403 => "bad boy >:(",
  404 => "not found :(",
  500 => "everything crashed :[",
  default => "something went wrong :/",
};
?>
<p class="request-error__status"><?= $status ?></p>
<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
<p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php if($developer): ?>
  <details>
    <summary><?= htmlspecialchars(get_class($error), ENT_QUOTES, 'UTF-8') ?> in <?= htmlspecialchars($error->getFile(), ENT_QUOTES, 'UTF-8') ?>:<?= $error->getLine() ?></summary>
    <pre><?= htmlspecialchars($error->getTraceAsString(), ENT_QUOTES, 'UTF-8') ?></pre>
  </details>
<?php endif ?>
