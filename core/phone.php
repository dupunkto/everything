<?php
// Phone number helpers.

function phone_region_options() {
  $util = \libphonenumber\PhoneNumberUtil::getInstance();
  $regions = $util->getSupportedRegions();
  sort($regions);

  return array_combine($regions, array_map(fn($region) =>
    "$region (+" . $util->getCountryCodeForRegion($region) . ")", $regions));
}

function parse_phone_number($value) {
  $value = preg_replace('/\p{Cf}/u', "", trim($value));

  try {
    return \libphonenumber\PhoneNumberUtil::getInstance()->parse($value, PHONE_REGION);
  }
  catch(\libphonenumber\NumberParseException) {
    return null;
  }
}

function normalize_phone_number($value) {
  $number = parse_phone_number($value);
  $util = \libphonenumber\PhoneNumberUtil::getInstance();

  if(!$number || !$util->isValidNumber($number)) {
    fail("Invalid phone number '$value'.", status: 400);
  }

  return $util->format($number, \libphonenumber\PhoneNumberFormat::E164);
}

function format_phone_number($value) {
  $number = parse_phone_number($value);
  if(!$number) return $value;

  $util = \libphonenumber\PhoneNumberUtil::getInstance();
  $format = $util->getRegionCodeForNumber($number) == PHONE_REGION
    ? \libphonenumber\PhoneNumberFormat::NATIONAL
    : \libphonenumber\PhoneNumberFormat::INTERNATIONAL;

  return $util->format($number, $format);
}
