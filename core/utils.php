<?php
// Various utility functions.

function generate_humid($length = 5, $base = 36) {
  return substr(str_pad(strtoupper(base_convert(unpack('N', random_bytes(4))[1], 10, $base)), $length, '0', STR_PAD_LEFT), -$length);
}

function circle($class = "") {
  ?>
  <svg class="circle <?= esc_attr($class) ?>" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
    <path d="M20 16 C45 5 80 8 92 30 C99 50 92 78 68 89 C42 99 12 93 6 64 C1 40 12 18 36 11"/>
    <path class="circle__ghost" d="M24 12 C48 3 82 6 95 29 C102 49 95 80 70 92 C44 102 12 95 4 66 C-2 41 10 15 33 9"/>
  </svg>
  <?php
}

function local_date($format, $timestamp = "now", $timezone = null) {
  $timezone = $timezone ?? TIMEZONE;
  $datetime = new DateTimeImmutable($timestamp, new DateTimeZone($timezone));
  return $datetime->format($format);
}

function address_line($a) {
  $parts = [
    trim(($a['street_name'] ?? '') . " " . ($a['street_number'] ?? '')),
    trim(($a['postal_code'] ?? '') . " " . ($a['city'] ?? '')),
    $a['province'] ?? '',
    $a['country'] ?? '',
  ];

  return str_implode(", ", $parts);
}

function maps_url($destination) {
  $to = rawurlencode($destination);

  return match(MAP_PROVIDER) {
    'google_maps' => "https://www.google.com/maps/dir/?api=1&destination=$to",
    'apple_maps' => "https://maps.apple.com/?daddr=$to",
    'openstreetmap' => "https://www.openstreetmap.org/directions?to=$to",
    default => null,
  };
}

function star_sign($month, $day) {
  $signs = ['Capricorn', 'Aquarius', 'Pisces', 'Aries', 'Taurus', 'Gemini',
            'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn'];
  // Last day the earlier sign still runs, per month (Capricorn wraps Dec -> Jan).
  $cutoff = [19, 18, 20, 19, 20, 20, 22, 22, 21, 22, 21, 20];
  return $day <= $cutoff[$month - 1] ? $signs[$month - 1] : $signs[$month];
}

function describe_recurrence($recurrence) {
  if($recurrence === null || $recurrence === "") return null;

  if(ctype_digit((string)$recurrence)) {
    return $recurrence == 1 ? "Every day" : "Every $recurrence days";
  }

  $fields = preg_split('/\s+/', trim($recurrence));
  if(count($fields) != 5) return $recurrence;

  [$minute, $hour, $dom, $month, $dow] = $fields;

  if(preg_match('/[^\d,*]/', implode("", $fields))) return $recurrence;
  if(!ctype_digit($minute) || !ctype_digit($hour)) return $recurrence;

  $time = sprintf("%02d:%02d", $hour, $minute);

  $list = fn($items) => count($items) > 1
    ? implode(", ", array_slice($items, 0, -1)) . " and " . end($items)
    : $items[0];

  if($dow != "*") {
    $weekdays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
    $names = array_map(fn($day) => $weekdays[$day % 7], explode(",", $dow));
    return "Every " . $list($names) . " at $time";
  }

  if($dom != "*" && $month != "*") {
    if(!ctype_digit($dom) || !ctype_digit($month) || $month < 1 || $month > 12) return $recurrence;
    $months = ["January", "February", "March", "April", "May", "June",
               "July", "August", "September", "October", "November", "December"];
    return "Every year on $dom " . $months[$month - 1] . " at $time";
  }

  if($dom != "*") {
    return "Every month on day " . $list(explode(",", $dom)) . " at $time";
  }

  return "Every day at $time";
}
