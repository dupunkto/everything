<?php $logs = \store\list_logs() ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Logs</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/logs.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--wide main--scrollable logs">
      <header class="page-header">
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
          <tbody>
            <?php foreach($logs as $log): ?>
              <?php if($log['source'] == 'caldav_changes') continue ?>
              <?php $entity = $log['table_name'] . "/" . $log['record_id'] ?>
              <?php $datetime = (new \DateTimeImmutable($log['changed_at'], timezone: new \DateTimeZone("UTC")))->format('c') ?>
              <tr>
                <td><time datetime="<?= esc_attr($datetime) ?>" local><?= esc_inner($log['changed_at']) ?> UTC</time></td>
                <td><?= esc_inner($log['message']) ?></td>
                <td><?= esc_inner($log['operation']) ?></td>
                <td><?= esc_inner($log['author']) ?></td>
                <td><span class="logs__entity" title="<?= esc_attr($entity) ?>"><?= esc_inner($entity) ?></span></td>
              </tr>
            <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </main>
  </body>
</html>
