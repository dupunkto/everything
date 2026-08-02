<?php
// Helpers for the contacts application.
// This file was lovingly written by Claude.

namespace contacts;

define('SOCIALS', [
  'instagram' => 'Instagram',
  'discord' => 'Discord',
  'snapchat' => 'Snapchat',
  'spacehey' => 'SpaceHey',
  'airbuds' => 'Airbuds',
  'tiktok' => 'TikTok',
  'wattpad' => 'Wattpad',
  'github' => 'GitHub',
  'codeberg' => 'Codeberg',
  'gitlab' => 'GitLab',
  'linkedin' => 'LinkedIn',
  'matrix' => 'Matrix',
  'pinterest' => 'Pinterest',
  'flickr' => 'Flickr',
  'twitter' => 'Twitter',
  'youtube' => 'YouTube',
  'facebook' => 'Facebook',
  'activitypub' => 'Mastodon',
  'bsky' => 'Bluesky',
]);

function social_label($type) {
  return @SOCIALS[$type] ?: ucfirst($type);
}

// The display name is generated: the first name plus the surname that
// name_order puts first. The display_name column still wins when set, but
// nothing writes it from the app anymore; nicknames took its place.
function contact_display_name($contact) {
  if(is_str(@$contact['display_name'])) return $contact['display_name'];
  if(CONTACTS_PREFER_NICKNAME && is_str(@$contact['nickname'])) return $contact['nickname'];

  $surname = contact_display_surname($contact);
  $first = $contact['first_name'];

  return CONTACTS_DISPLAY_FORMAT == 'last_first' && $surname && $first
    ? "$surname, $first"
    : str_implode(" ", [$first, $surname]);
}

function contact_listing_name($contact) {
  return contact_display_name($contact);
}

function contact_display_surname($contact) {
  if($contact['name_order'] == 'legal_family' && is_str(@$contact['legal_name']))
    return str_implode(" ", [$contact['legal_infix'], $contact['legal_name']]);

  return contact_family_name($contact);
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

function social_url($type, $handle, $kind = 'person') {
  $raw = trim($handle);
  $h = ltrim($raw, "@");
  $enc = rawurlencode($h);
  $ap = explode("@", $h); // activitypub: user@instance

  return match($type) {
    'instagram' => "https://instagram.com/$enc",
    'discord' => ctype_digit($h) ? "https://discord.com/users/$enc" : null,
    'snapchat' => "https://snapchat.com/add/$enc",
    'spacehey' => ctype_digit($h) ? "https://spacehey.com/profile?id=$enc" : "https://spacehey.com/$enc",
    'airbuds' => "https://i.airbuds.fm/$enc",
    'tiktok' => "https://tiktok.com/@$enc",
    'wattpad' => "https://wattpad.com/user/$enc",
    'github' => "https://github.com/$enc",
    'codeberg' => "https://codeberg.org/$enc",
    'gitlab' => "https://gitlab.com/$enc",
    'linkedin' => $kind == "org" ? "https://linkedin.com/company/$enc" : "https://linkedin.com/in/$enc",
    'matrix' => "https://matrix.to/#/" . rawurlencode($raw),
    'pinterest' => "https://pinterest.com/$enc",
    'twitter' => "https://twitter.com/$enc",
    'youtube' => "https://youtube.com/@$enc",
    'facebook' => "https://facebook.com/$enc",
    'activitypub' => count($ap) == 2 ? "https://{$ap[1]}/@{$ap[0]}" : null,
    'bsky' => "https://bsky.app/profile/$enc",
    default => null,
  };
}
