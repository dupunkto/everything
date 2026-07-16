<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>About</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
    <script src="<?= CANONICAL ?>/client/draggable.js" defer></script>
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main about">
      <hgroup class="about-heading">
        <h1>Everything</h1>
        <p>a <a href="//dupunkto.org">{du}punkto</a> project</p>
      </hgroup>
      
      <p class="about-version">
        <span>v<?= EVERYTHING_VERSION ?></span> &middot;
        <?php if(defined('GIT_SHA')): ?>
          <span><a href="//git.dupunkto.org/dupunkto/everything/commit/<?= GIT_SHA ?>"><?= substr(GIT_SHA, 0, 7) ?></a></span>
        <?php else: ?>
          <span><a href="//git.dupunkto.org/dupunkto/everything">Source code</a></span>
        <?php endif; ?>
      </p>
    </main>
  </body>
</html>