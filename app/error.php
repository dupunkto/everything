<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if(defined('CANONICAL')): ?>
      <link rel="stylesheet" href="<?= htmlspecialchars(CANONICAL, ENT_QUOTES, 'UTF-8') ?>/css/main.css">
    <?php endif ?>
    <title>Error <?= error_status($error) ?></title>
  </head>
  <body>
    <main class="main request-error request-error--page">
      <?php include __DIR__ . "/error/content.php" ?>
    </main>
  </body>
</html>
