<?php
// Core Everything APIs live here.

define('EVERYTHING_VERSION', "0.1-preview");
define('STORE_VERSION', 1);

require __DIR__ . "/core/anyhow.php";
require __DIR__ . "/core/neuro.php";

require __DIR__ . "/vendor/sabre.php";
require __DIR__ . "/vendor/parsedown.php";
require __DIR__ . "/vendor/html2md.php";
require __DIR__ . "/vendor/libphonenumber.php";

require __DIR__ . "/store.php";
require __DIR__ . "/config.php";
require __DIR__ . "/init.php";
require __DIR__ . "/git.php";

require __DIR__ . "/core/core.php";
require __DIR__ . "/core/logger.php";
require __DIR__ . "/core/cast.php";
require __DIR__ . "/core/ui.php";
require __DIR__ . "/core/forms.php";
require __DIR__ . "/core/dates.php";
require __DIR__ . "/core/geo.php";
require __DIR__ . "/core/phone.php";
require __DIR__ . "/core/astro.php";
require __DIR__ . "/core/imap.php";
require __DIR__ . "/core/icalendar.php";
require __DIR__ . "/core/webdav.php";
require __DIR__ . "/core/caldav.php";
require __DIR__ . "/core/carddav.php";
require __DIR__ . "/core/utils.php";

require __DIR__ . "/core/logic/recurrence.php";
require __DIR__ . "/core/logic/contacts.php";
require __DIR__ . "/core/logic/calendar.php";
require __DIR__ . "/core/logic/habits.php";
require __DIR__ . "/core/logic/shares.php";
require __DIR__ . "/core/logic/quotas.php";
require __DIR__ . "/core/logic/bookmarks.php";
require __DIR__ . "/core/logic/mcp.php";
require __DIR__ . "/core/logic/export.php";


require __DIR__ . "/core/syncer/notes.php";
