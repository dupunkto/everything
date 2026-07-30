<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= error_title($error) ?></title>

    <link rel="stylesheet" href="https://cdn.dupunkto.org/tools.css">

    <?php if(defined('CANONICAL')): ?>
      <link rel="stylesheet" href="<?= CANONICAL ?>/css/main.css">
    <?php endif ?>
  </head>
  <body>
    <main class="main request-error request-error--page">
      <h1 class="request-error__title"><span class="request-error__status"><?= $status ?></span> <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
      <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <?php if($developer): ?>
        <details class="request-error__trace">
          <summary><?= htmlspecialchars(get_class($error), ENT_QUOTES, 'UTF-8') ?> in <?= htmlspecialchars($error->getFile(), ENT_QUOTES, 'UTF-8') ?>:<?= $error->getLine() ?></summary>
          <pre><?= htmlspecialchars($error->getTraceAsString(), ENT_QUOTES, 'UTF-8') ?></pre>
        </details>
      <?php endif ?>
    </main>
  </body>
</html>
