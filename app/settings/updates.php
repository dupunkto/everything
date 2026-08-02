<?php

if(!defined('GIT_SHA')) fail("Updates are only available when running from a Git clone.", status: 404);

?>
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
        <?php $parent = "/settings/general"; include __DIR__ . "/back.php" ?>
        <h2>Updates</h2>
      </header>

      <section id="updates-status" x-get="/settings/updates/status">
        <?php fragment("settings/updates/status") ?>
      </section>
    </main>
  </body>
</html>
