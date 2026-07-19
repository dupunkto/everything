<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <h2>Contacts</h2>
      </header>

      <section id="contacts-settings" x-get="/settings/contacts/edit"><?php fragment("settings/contacts/edit") ?></section>
    </main>
  </body>
</html>
