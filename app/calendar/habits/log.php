<?php

if(!isset($_POST['id'], $_POST['date'])) {
  fail("Could not complete request: missing POST data.", status: 400);
}

$habit = \store\get_habit($_POST['id']) or fail("Habit not found.", status: 404);
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $_POST['date']);

if(!$date || $date->format('Y-m-d') != $_POST['date']) {
  fail("Invalid date.", status: 400);
}

$done = \store\get_habit_log($habit['id'], $_POST['date']);

$done
  ? \store\unlog_habit($habit['id'], $_POST['date'])
  : \store\log_habit($habit['id'], $_POST['date']);

\store\put_audit_log('habits', $habit['id'], "Updated [log] for habits/{$habit['id']}.", 'user');

$habit['date'] = $_POST['date'];
$habit['done'] = !$done;
$habit['contrast_color'] = contrast_color(
  $habit['color'],
  lighten($habit['color'], 0.85),
  darken($habit['color'], 0.65)
);

include __DIR__ . "/button.php";
