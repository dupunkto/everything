<?php

$url = parse_url(CANONICAL);

$host = @$url['host'] or fail("The canonical URL has no host.");
$ssl = @$url['scheme'] == 'https';
$port = @$url['port'] ?: ($ssl ? 443 : 80);

$identifier = "org.dupunkto.everything." . substr(hash('sha256', CANONICAL), 0, 16);

$host = \webdav\text($host);
$username = \webdav\text($_AUTH_USER);
$ssl = $ssl ? '<true/>' : '<false/>';

header("Content-Type: application/x-apple-aspen-config");
header('Content-Disposition: attachment; filename="everything.mobileconfig"');
header("Cache-Control: private, no-store");

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>PayloadContent</key>
  <array>
    <dict>
      <key>PayloadType</key>
      <string>com.apple.caldav.account</string>
      <key>PayloadVersion</key>
      <integer>1</integer>
      <key>PayloadIdentifier</key>
      <string><?= $identifier ?>.caldav</string>
      <key>PayloadUUID</key>
      <string><?= derive_uuid("$identifier.caldav") ?></string>
      <key>PayloadDisplayName</key>
      <string>Everything calendars</string>
      <key>CalDAVAccountDescription</key>
      <string>Everything</string>
      <key>CalDAVHostName</key>
      <string><?= $host ?></string>
      <key>CalDAVPort</key>
      <integer><?= $port ?></integer>
      <key>CalDAVPrincipalURL</key>
      <string>/caldav/principals/<?= CALDAV_PRINCIPAL ?>/</string>
      <key>CalDAVUseSSL</key>
      <?= $ssl ?>
      <key>CalDAVUsername</key>
      <string><?= $username ?></string>
    </dict>
    <dict>
      <key>PayloadType</key>
      <string>com.apple.carddav.account</string>
      <key>PayloadVersion</key>
      <integer>1</integer>
      <key>PayloadIdentifier</key>
      <string><?= $identifier ?>.carddav</string>
      <key>PayloadUUID</key>
      <string><?= derive_uuid("$identifier.carddav") ?></string>
      <key>PayloadDisplayName</key>
      <string>Everything contacts</string>
      <key>CardDAVAccountDescription</key>
      <string>Everything</string>
      <key>CardDAVHostName</key>
      <string><?= $host ?></string>
      <key>CardDAVPort</key>
      <integer><?= $port ?></integer>
      <key>CardDAVPrincipalURL</key>
      <string>/carddav/principals/<?= CARDDAV_PRINCIPAL ?>/</string>
      <key>CardDAVUseSSL</key>
      <?= $ssl ?>
      <key>CardDAVUsername</key>
      <string><?= $username ?></string>
    </dict>
  </array>
  <key>PayloadType</key>
  <string>Configuration</string>
  <key>PayloadVersion</key>
  <integer>1</integer>
  <key>PayloadIdentifier</key>
  <string><?= $identifier ?></string>
  <key>PayloadUUID</key>
  <string><?= derive_uuid($identifier) ?></string>
  <key>PayloadDisplayName</key>
  <string>Everything</string>
  <key>PayloadDescription</key>
  <string>Adds Everything calendars and contacts.</string>
</dict>
</plist>
