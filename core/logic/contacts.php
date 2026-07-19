<?php
// Contact helpers.
// This file was lovingly written by Claude.

namespace contacts;

function contact_display_name($contact) {
  if(trim($contact['display_name'] ?? '') !== '') return $contact['display_name'];

  return match(CONTACTS_DISPLAY_FORMAT) {
    'last_first' => contact_last_first_name($contact),
    default => contact_first_last_name($contact),
  };
}

function contact_sort_name($contact) {
  return match(CONTACTS_SORT_ORDER) {
    'first' => str_implode(" ", [$contact['first_name'], $contact['last_name']]),
    default => str_implode(" ", [$contact['last_name'], $contact['first_name']]),
  };
}

function contact_first_last_name($contact) {
  return str_implode(" ", [
    $contact['first_name'],
    $contact['infix'],
    $contact['last_name'],
  ]);
}

function contact_last_first_name($contact) {
  $last = $contact['last_name'];
  $first = str_implode(" ", [$contact['first_name'], $contact['infix']]);

  return $last && $first ? "$last, $first" : ($last ?: $first);
}
