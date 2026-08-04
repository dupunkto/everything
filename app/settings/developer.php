<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Developer settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main class="main">
      <header class="page-header">
        <?php $parent = "/settings"; include __DIR__ . "/back.php" ?>
        <h2>Developer</h2>
      </header>

      <ul class="settings-menu">
        <li><a href="<?= CANONICAL ?>/logs">Logs</a></li>
        <li><a href="<?= CANONICAL ?>/settings/developer/git">Git</a></li>
        <li><a href="<?= CANONICAL ?>/settings/developer/custom-code">Custom code</a></li>
      </ul>

      <form x-post="/settings/developer/clear-notes">
        <button type="submit" z-confirm="Clear every Apple UUID and tombstone? This will require an empty IMAP account, otherwise you will end up with duplicate notes.">Clear IMAP sync state</button>
      </form>

      <form x-post="/settings/developer/clear-http-log">
        <button type="submit" z-confirm="Delete every HTTP log entry?">Truncate HTTP log</button>
      </form>

      <form x-post="/settings/developer/clear-system-log">
        <button type="submit" z-confirm="Delete every system log entry?">Truncate system log</button>
      </form>
    </main>
  </body>
</html>
