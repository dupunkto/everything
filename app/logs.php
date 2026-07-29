<?php
  $level = in_array(@$_GET['level'], ['debug', 'info', 'warn', 'error']) ? $_GET['level'] : 'info';
  $sources = isset($_GET['filters']) ? (array)@$_GET['source'] : ['audit', 'system'];
?>
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
      <header>
        <form id="logs-filters" class="logs-header" method="get" action="/logs"
          x-get="/logs" x-on="input" x-target="@document">
          <input type="hidden" name="filters" value="1">
          <h1 class="logs-header__title"><strong>Logs</strong></h1>
          <input class="logs-header__message" type="search" name="message" placeholder="Message" aria-label="Message" value="<?= esc_attr(@$_GET['message']) ?>">
          <input type="datetime-local" name="from" step="1" aria-label="From" title="From" value="<?= esc_attr(@$_GET['from']) ?>" hidden>
          <input type="datetime-local" name="to" step="1" aria-label="To" title="To" value="<?= esc_attr(@$_GET['to']) ?>" hidden>

          <input type="hidden" name="level" value="<?= esc_attr($level) ?>">
          <div class="logs-header__buttons logs-header__levels">
            <?php foreach(['error', 'warn', 'info', 'debug'] as $candidate): ?>
              <?php $active = array_search($candidate, ['debug', 'info', 'warn', 'error']) >= array_search($level, ['debug', 'info', 'warn', 'error']) ?>
              <button class="logs-filter logs-filter--<?= esc_attr($candidate) ?><?php if($active) echo ' logs-filter--active' ?>" type="submit" name="level" value="<?= esc_attr($candidate) ?>"><?= esc_inner($candidate) ?></button>
            <?php endforeach ?>
          </div>

          <div class="logs-header__buttons logs-header__sources">
            <?php foreach(['audit', 'system', 'http'] as $source): ?>
              <label class="logs-filter<?php if(in_array($source, $sources)) echo ' logs-filter--active' ?>">
                <input type="checkbox" name="source[]" value="<?= esc_attr($source) ?>" <?php if(in_array($source, $sources)) echo "checked" ?>>
                <?= esc_inner($source) ?>
              </label>
            <?php endforeach ?>
          </div>

          <input class="logs-header__limit" type="number" name="limit" min="1" placeholder="Limit" aria-label="Limit" value="<?= esc_attr(@$_GET['limit'] ?: 1000) ?>">
        </form>
      </header>

      <div class="main__scroll">
        <table class="logs__table">
          <thead>
            <tr>
              <th>level</th>
              <th>datetime</th>
              <th>message</th>
              <th>operation</th>
              <th>author</th>
              <th>entity</th>
            </tr>
          </thead>
          <tbody id="logs-listing"><?php fragment("logs/listing") ?></tbody>
        </table>
      </div>
    </main>
  </body>
</html>
