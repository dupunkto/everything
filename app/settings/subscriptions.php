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
        <h2>Subscriptions</h2>
        <a class="button" z-toggle="#subscription-new">Add subscription</a>
      </header>

      <section id="subscription-new" x-get="/settings/subscriptions/new" z-dismiss="escape" hidden></section>
      <section id="subscriptions-listing" x-get="/settings/subscriptions/listing"></section>
    </main>
  </body>
</html>