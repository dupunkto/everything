<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Accounts</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings"; include __DIR__ . "/back.php" ?>
        <h2>Accounts</h2>
      </header>

      <ul class="settings-menu">
        <li><a>IMAP</a></li>
        <li><a>SMTP</a></li>
      </ul>
    </main>
  </body>
</html>
