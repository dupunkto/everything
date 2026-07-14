<?php
// Various utility functions.

function generate_humid($length = 5, $base = 36) {
  return substr(str_pad(strtoupper(base_convert(unpack('N', random_bytes(4))[1], 10, $base)), $length, '0', STR_PAD_LEFT), -$length);
}
