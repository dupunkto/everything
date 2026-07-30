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
      <?php include __DIR__ . "/error/content.php" ?>
    </main>
  </body>
</html>
