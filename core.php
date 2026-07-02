<?php
// Core Everything APIs live here.

define('EVERYTHING_VERSION', "0.1");
define('STORE_VERSION', 0);

require __DIR__ . "/store.php";
require __DIR__ . "/config.php";
require __DIR__ . "/init.php";

require __DIR__ . "/core/core.php";
require __DIR__ . "/core/neuro.php";
require __DIR__ . "/core/forms.php";
require __DIR__ . "/core/utils.php";

// sync.php uses http\ and logger\ from neuro, so it loads after neuro.
require __DIR__ . "/store/sync.php";
