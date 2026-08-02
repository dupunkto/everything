<?php
// Various utility functions.

function generate_humid($length = 5, $base = 36) {
  return substr(str_pad(strtoupper(base_convert(unpack('N', random_bytes(4))[1], 10, $base)), $length, '0', STR_PAD_LEFT), -$length);
}

function generate_uuid() {
  $bytes = random_bytes(16);
  $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
  $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function markdown($text) {
  static $parsedown;
  $parsedown ??= (new Parsedown)->setSafeMode(true)->setBreaksEnabled(true);

  return $parsedown->text($text);
}

function tag_slug($label) {
  return slugify(str_replace(["{", "}", "(", ")", "[", "]"], "", $label));
}

function format_price_value($price) {
  return $price === null ? "" : number_format((float)$price, 2, '.', '');
}

function format_price($price, $currency = null) {
  $currency = strtolower($currency ?? \config\fresh_value('currency'));
  $symbol = CURRENCY_SYMBOLS[$currency] ?? strtoupper($currency);

  return $symbol . format_price_value($price);
}
