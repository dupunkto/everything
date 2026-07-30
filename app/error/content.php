<h1 class="request-error__title"><span class="request-error__status"><?= $status ?></span> <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
<p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
<?php if($developer): ?>
  <details class="request-error__trace">
    <summary><?= htmlspecialchars(get_class($error), ENT_QUOTES, 'UTF-8') ?> in <?= htmlspecialchars($error->getFile(), ENT_QUOTES, 'UTF-8') ?>:<?= $error->getLine() ?></summary>
    <pre><?= htmlspecialchars($error->getTraceAsString(), ENT_QUOTES, 'UTF-8') ?></pre>
  </details>
<?php endif ?>
