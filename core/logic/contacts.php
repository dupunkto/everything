<?php
// Helpers for the contacts application.
// This file was lovingly written by Claude.

namespace contacts;

function contact_display_name($contact) {
  if(trim($contact['display_name'] ?? '') != '') return $contact['display_name'];

  return match(CONTACTS_DISPLAY_FORMAT) {
    'last_first' => contact_last_first_name($contact),
    default => contact_first_last_name($contact),
  };
}

function contact_listing_name($contact) {
  if(trim($contact['display_name'] ?? '') != '') return $contact['display_name'];

  $family = contact_family_name($contact);
  $first = $contact['first_name'];

  return CONTACTS_DISPLAY_FORMAT == 'last_first' && $family && $first
    ? "$family, $first"
    : str_implode(" ", [$first, $family]);
}

function contact_sort_name($contact) {
  return match(CONTACTS_SORT_ORDER) {
    'first' => str_implode(" ", [$contact['first_name'], $contact['family_name']]),
    default => str_implode(" ", [$contact['family_name'], $contact['first_name']]),
  };
}

function contact_family_name($contact) {
  return str_implode(" ", [$contact['family_infix'], $contact['family_name']]);
}

function contact_surname($contact) {
  $legal = str_implode(" ", [$contact['legal_infix'], $contact['legal_name']]);
  $family = contact_family_name($contact);

  return match($contact['name_order']) {
    'legal_family' => str_implode("-", [$legal, $family]),
    default => str_implode("-", [$family, $legal]),
  };
}

function contact_first_last_name($contact) {
  return str_implode(" ", [$contact['first_name'], contact_surname($contact)]);
}

function contact_last_first_name($contact) {
  $last = contact_surname($contact);
  $first = $contact['first_name'];

  return $last && $first ? "$last, $first" : ($last ?: $first);
}
