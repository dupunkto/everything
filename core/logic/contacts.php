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

function normalize_binary_picture($content) {
  if(!is_string($content) || $content == "" || strlen($content) > ini_parse_quantity(CONTACTS_UPLOAD_LIMIT))
    throw new \InvalidArgumentException("Profile picture must be no larger than " . CONTACTS_UPLOAD_LIMIT . ".");

  $info = @getimagesizefromstring($content);

  if(!$info || !in_array($info['mime'], CONTACTS_PICTURE_MIMES))
    throw new \InvalidArgumentException("Profile picture must be a JPEG, PNG, WebP or GIF image.");

  if($info[0] * $info[1] > CONTACTS_PICTURE_MAX_PIXELS)
    throw new \InvalidArgumentException("Profile picture dimensions are too large.");

  $source = @imagecreatefromstring($content);
  if(!$source) throw new \InvalidArgumentException("Profile picture could not be decoded.");

  $scale = min(1, CONTACTS_PICTURE_MAX_EDGE / max($info[0], $info[1]));
  $width = max(1, (int)round($info[0] * $scale));
  $height = max(1, (int)round($info[1] * $scale));
  $image = imagecreatetruecolor($width, $height);

  if(in_array($info['mime'], ['image/png', 'image/webp', 'image/gif'])) {
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    if($info['mime'] == 'image/gif') imagecolortransparent($image, $transparent);
  }

  imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
  imagedestroy($source);

  ob_start();
  $written = match($info['mime']) {
    'image/jpeg' => imagejpeg($image, null, 85),
    'image/png' => imagepng($image, null, 6),
    'image/webp' => imagewebp($image, null, 85),
    'image/gif' => imagegif($image),
  };
  $normalized = ob_get_clean();
  imagedestroy($image);

  if(!$written || $normalized == "")
    throw new \InvalidArgumentException("Profile picture could not be processed.");

  return ['mime_type' => $info['mime'], 'content' => $normalized];
}

function normalize_base64_picture($content) {
  $decoded = base64_decode(preg_replace('/\s+/', '', $content), true);
  if($decoded === false) throw new \InvalidArgumentException("VCARD PHOTO contains invalid base64 data.");
  return normalize_binary_picture($decoded);
}

function normalize_remote_picture($url) {
  $response = \http\get($url);

  if($response['state'] != 'success' || $response['status'] < 200 || $response['status'] >= 300) {
    \logger\warn("Dropped profile picture: remote URL could not be fetched.");
    return null;
  }

  return normalize_binary_picture($response['body']);
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
