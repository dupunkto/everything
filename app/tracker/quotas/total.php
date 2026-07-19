<?php

  $now = new \DateTimeImmutable("now", new \DateTimeZone(TIMEZONE));
  $rows = \quotas\total($now, \store\list_quotas());

?>
<header class="page-header">
  <h2>Total</h2>
</header>
<?php include __DIR__ . "/table.php" ?>
