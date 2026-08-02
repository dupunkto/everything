<?php

  $share = \store\get_share($_POST['id']) or fail("Share not found.", status: 404);
  $name = cast_str(@$_POST['name']) or fail("A share name is required.", status: 400);
  $available = array_column(\shares\sources(), null, 'key');
  $sources = [];
  $seen = [];

  foreach(@$_POST['source'] ?: [] as $i => $key) {
    $mode = @$_POST['mode'][$i];
    if(!isset($available[$key]) || !in_array($mode, ['full', 'redacted']))
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

  $birthdays = cast_bool(@$_POST['birthdays']);
  $deadlines = cast_bool(@$_POST['deadlines']);
  $fields = \core\diff($share,
    name: $name, birthdays: $birthdays, deadlines: $deadlines);

  \store\update_share($share['id'], $name, $birthdays, $deadlines);
  \store\set_share_sources($share['id'], $sources);
  \store\put_audit_log('shares', $share['id'],
    "Updated [" . join(", ", [...$fields, 'sources']) . "] for shares/{$share['id']}.", 'user');

  include __DIR__ . "/listing.php"; exit;
