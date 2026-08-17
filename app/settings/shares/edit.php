<?php

  define('ENUM_SHARE_MODE', ['full', 'redacted']);

  $share = \store\get_share($_POST['id'])
    or fail("Share not found.", status: 404);

  $available = array_column(\shares\sources(), null, 'key');
  $sources = [];
  $seen = [];

  foreach(@$_POST['source'] ?: [] as $i => $key) {
    $mode = @$_POST['mode'][$i];

    if(!isset($available[$key]) || !in_array($mode, ENUM_SHARE_MODE))
      fail("Invalid share source.", status: 400);

    if(isset($seen[$key])) fail("A source can only be included once.", status: 400);

    $seen[$key] = true;
    $source = $available[$key];
    $sources[] = [
      'calendar_id' => $source['type'] == 'calendar' ? $source['id'] : null,
      'subscription_id' => $source['type'] == 'subscription' ? $source['id'] : null,
      'mode' => $mode,
    ];
  }

  $fields = \core\diff($share,
    name: cast_str($_POST['name']),
    birthdays: cast_bool(@$_POST['birthdays']),
    deadlines: cast_bool(@$_POST['deadlines'])
  );

  \store\update_share(
    $share['id'],
    cast_str($_POST['name']),
    cast_bool(@$_POST['birthdays']),
    cast_bool(@$_POST['deadlines'])
  );

  \store\set_share_sources($share['id'], $sources);

  \store\put_audit_log('shares', $share['id'],
    "Updated [" . join(", ", [...$fields, 'sources']) . "] for shares/{$share['id']}.", 'user');

  include __DIR__ . "/listing.php"; exit;
