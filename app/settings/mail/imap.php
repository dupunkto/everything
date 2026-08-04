<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../../shell/head.php" ?>
    <title>IMAP accounts</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings/mail"; include __DIR__ . "/../back.php" ?>
        <h2>IMAP accounts</h2>
        <a class="button" z-toggle="#account-new">Add account</a>
      </header>

      <section id="account-new" x-get="/settings/mail/imap/new" z-dismiss="escape" hidden><?php fragment("settings/mail/imap/new") ?></section>
      <section id="imap-accounts" x-get="/settings/mail/imap/listing"><?php fragment("settings/mail/imap/listing") ?></section>
    </main>
  </body>
</html>
