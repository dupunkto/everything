<?php

$environment = match($_ENV) {
  "dev" => "development",
  "prod" => "production",
  default => $_ENV
};

$phpinfo = capture('phpinfo', INFO_GENERAL);

preg_match('/<td class="e">Build Provider\s*<\/td><td class="v">(.*?)<\/td>/s', $phpinfo, $build_provider);
$build_provider = isset($build_provider[1]) ? trim(html_entity_decode(strip_tags($build_provider[1]))) : "unknown";

$about_rows = [
  ["Environment", $environment],
  ["Canonical", CANONICAL],
  ["Server protocol", $_SERVER['SERVER_PROTOCOL'] ?? "unknown"],
  ["Database driver", $_DATABASE['scheme']],
  ["PHP version", PHP_VERSION],
  ["PHP SAPI", PHP_SAPI],
  ["System kernel", php_uname('s')],
  ["Build provider", $build_provider]
];

?>
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
        <span>v<?= EVERYTHING_VERSION ?>-<?= STORE_VERSION ?></span> &middot;
        <?php if(defined('GIT_SHA')): ?>
          built from <span><a href="//git.dupunkto.org/dupunkto/everything/commit/<?= GIT_SHA ?>"><?= substr(GIT_SHA, 0, 7) ?></a></span>
        <?php else: ?>
          <span><a href="//git.dupunkto.org/dupunkto/everything">Source code</a></span>
        <?php endif; ?>
      </p>

      <table class="about-info">
        <tbody>
          <?php foreach($about_rows as [$label, $value]): ?>
            <tr>
              <th><?= esc_inner($label) ?></th>
              <td><?= esc_inner($value) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <a class="about-licenses">License information &rarr;</a>
    </main>
  </body>
</html>