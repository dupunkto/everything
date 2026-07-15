<?php

  if(isset($_POST["id"], $_POST["title"], $_POST["every"], $_POST["color"], $_POST["icon"])) {
    $icons = preg_split('/\s+/', cast_string($_POST['custom_icon']) ?? cast_string($_POST['icon']) ?? 'fa-circle-check');
    $icon = end($icons);
    if(!str_starts_with($icon, 'fa-')) $icon = 'fa-' . $icon;

    \store\update_habit($_POST['id'], cast_string($_POST['title']), cast_string($_POST['every']), cast_color($_POST['color']), $icon)
      or fail("Could not save habit #" . $_POST['id'] . ".");

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
