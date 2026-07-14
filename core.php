<?php
// Core Everything APIs live here.

define('EVERYTHING_VERSION', "0.1");
define('STORE_VERSION', 0);

require __DIR__ . "/store.php";
require __DIR__ . "/config.php";
require __DIR__ . "/init.php";

require __DIR__ . "/core/core.php";
require __DIR__ . "/core/neuro.php";
require __DIR__ . "/core/cast.php";
require __DIR__ . "/core/ui.php";
require __DIR__ . "/core/forms.php";
require __DIR__ . "/core/dates.php";
require __DIR__ . "/core/geo.php";
require __DIR__ . "/core/astro.php";
require __DIR__ . "/core/ical.php";
require __DIR__ . "/core/utils.php";

require __DIR__ . "/core/logic/recurrence.php";
require __DIR__ . "/core/logic/calendar.php";
require __DIR__ . "/core/logic/subscription.php";
