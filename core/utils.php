<?php
// Various utility functions.

function generate_humid($length = 5, $base = 36) {
  return substr(str_pad(strtoupper(base_convert(unpack('N', random_bytes(4))[1], 10, $base)), $length, '0', STR_PAD_LEFT), -$length);
}

function format_price_value($price) {
  return $price === null ? "" : number_format((float)$price, 2, '.', '');
}

function format_price($price, $currency = null) {
  $currency = strtolower($currency ?? \config\fresh_value('currency'));
  $symbol = CURRENCY_SYMBOLS[$currency] ?? strtoupper($currency);

  return $symbol . format_price_value($price);
}
