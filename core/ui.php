<?php
// Shared UI components.

function circle($class = "") {
  ?>
  <svg class="circle <?= esc_attr($class) ?>" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
    <path d="M20 16 C45 5 80 8 92 30 C99 50 92 78 68 89 C42 99 12 93 6 64 C1 40 12 18 36 11"/>
    <path class="circle__ghost" d="M24 12 C48 3 82 6 95 29 C102 49 95 80 70 92 C44 102 12 95 4 66 C-2 41 10 15 33 9"/>
  </svg>
  <?php
}
