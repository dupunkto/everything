<?php

if($method == 'GET') {
  header("Content-Type: text/plain; charset=utf-8");
  echo \store\get_scratchpad();
  exit;
}

if($method == 'PUT') {
  \store\update_scratchpad(file_get_contents('php://input'));
  stay_on_page();
}

fail("Method not allowed.", status: 405);
