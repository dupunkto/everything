<?php
// Astrology helpers.

function star_sign($month, $day) {
  $signs = ['Capricorn', 'Aquarius', 'Pisces', 'Aries', 'Taurus', 'Gemini',
            'Cancer', 'Leo', 'Virgo', 'Libra', 'Scorpio', 'Sagittarius', 'Capricorn'];
  // Last day the earlier sign still runs, per month (Capricorn wraps Dec -> Jan).
  $cutoff = [19, 18, 20, 19, 20, 20, 22, 22, 21, 22, 21, 20];
  return $day <= $cutoff[$month - 1] ? $signs[$month - 1] : $signs[$month];
}
