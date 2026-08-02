<?php

  if(!in_array($method, ['GET', 'HEAD'])) fail("Method not allowed.", status: 405);

  $share = \store\get_share_by_token($params[1]) or fail("Shared feed not found.", status: 404);
  $body = \shares\serialize($share);

  header("Content-Type: text/calendar; charset=utf-8");
  header('Content-Disposition: inline; filename="shared.ics"');
  header("Cache-Control: no-store");
  echo $body;
