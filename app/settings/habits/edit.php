<?php

  if(isset($_POST["id"], $_POST["title"], $_POST["every"], $_POST["color"], $_POST["icon"])) {
    $icons = preg_split('/\s+/', cast_str($_POST['custom_icon']) ?? cast_str($_POST['icon']) ?? 'fa-circle-check');
    $icon = end($icons);
    if(!str_starts_with($icon, 'fa-')) $icon = 'fa-' . $icon;

    $habit = \store\get_habit($_POST['id']) or fail("Habit not found.", status: 404);
    $fields = \core\diff($habit,
      title: cast_str($_POST['title']), every: cast_str($_POST['every']),
      color: cast_color($_POST['color']), icon: $icon);

    \store\update_habit($_POST['id'], cast_str($_POST['title']), cast_str($_POST['every']), cast_color($_POST['color']), $icon);
    \store\put_audit_log('habits', $_POST['id'], "Updated [" . join(", ", $fields) . "] for habits/{$_POST['id']}.", 'user');

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
