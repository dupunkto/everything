<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Logs</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/logs.css">
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--wide main--scrollable logs">
      <header class="page-header">
        <?php $parent = "/settings/developer"; include __DIR__ . "/settings/back.php" ?>
        <h1 class="page-header__title"><strong>Logs</strong></h1>
      </header>

      <div class="main__scroll">
        <table class="logs__table">
          <thead>
            <tr>
              <th>datetime</th>
              <th>message</th>
              <th>operation</th>
              <th>author</th>
              <th>entity</th>
            </tr>
          </thead>
          <tbody><?php fragment("logs/listing") ?></tbody>
        </table>
      </div>
    </main>
  </body>
</html>
