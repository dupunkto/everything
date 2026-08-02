<?php
  if(isset($_POST["id"], $_POST["label"], $_POST["parent"])) {
    $tag = \store\get_tag($_POST['id']) or fail("Tag not found.", status: 404);
    $fields = \core\diff($tag,
      label: cast_str($_POST['label']), color: cast_color(@$_POST['color']), parent_id: cast_num($_POST['parent']));

    \store\update_tag($_POST['id'], cast_str($_POST['label']), cast_color(@$_POST['color']), cast_num($_POST['parent']));

    \store\put_audit_log('tags', $_POST['id'], "Updated [" . join(", ", $fields) . "] for tags/{$_POST['id']}.", 'user');

    include __DIR__ . "/listing.php"; exit;
  }
  else {
    fail("Could not complete request: missing POST data.", status: 400);
  }
