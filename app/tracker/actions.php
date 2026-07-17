<?php if($path == "/tracker/quotas"): ?>
  <a href="/tracker" class="nav__action" title="Back to tracker"><i class="fa-regular fa-arrow-left"></i></a>
<?php elseif(\store\list_quotas()): ?>
  <a href="/tracker/quotas" class="nav__action" title="Quota overview"><i class="fa-solid fa-chart-simple"></i></a>
<?php endif ?>
<a href="/settings/tracker?back=/tracker" class="nav__action" title="Tracker settings"><i class="fa-regular fa-gear"></i></a>
