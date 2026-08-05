<?php
// Contact and organisation profile pictures.

$kind = @$_GET['kind'] ?: @$_POST['kind'];
$id = @$_GET['id'] ?: @$_POST['id'];

if(!in_array($kind, ['person', 'org']))
  fail("Malformed profile picture target.", status: 400);

$type = $kind == 'org' ? 'organisation' : 'contact';
$table = $kind == 'org' ? 'organisations' : 'contacts';
$item = $kind == 'org' ? \store\get_organisation($id) : \store\get_contact($id);

if(!$item) fail("Profile picture owner not found.", status: 404);

if($method == 'GET' || $method == 'HEAD') {
  $picture = \store\get_profile_picture($type, $id)
    or fail("Profile picture not found.", status: 404);

  header("Content-Type: {$picture['mime_type']}");
  header('Content-Length: ' . strlen($picture['content']));
  header('ETag: "' . $picture['content_hash'] . '"');
  header("Cache-Control: private, max-age=31536000, immutable");

  if($method == 'GET') echo $picture['content']; exit;
}

if($method == 'POST') {
  $file = @$_FILES['picture'];

  if(!$file || $file['error'] != UPLOAD_ERR_OK) {
    $message = match(@$file['error']) {
      UPLOAD_ERR_INI_SIZE,
      UPLOAD_ERR_FORM_SIZE => "Profile picture must be no larger than " . CONTACTS_UPLOAD_LIMIT . ".",
      UPLOAD_ERR_NO_FILE => "Choose a profile picture.",
      default => "Profile picture upload failed.",
    };

    fail($message, status: 400);
  }

  if($file['size'] > ini_parse_quantity(CONTACTS_UPLOAD_LIMIT))
    fail("Profile picture must be no larger than " . CONTACTS_UPLOAD_LIMIT . ".", status: 400);

  $picture = \contacts\normalize_binary_picture(file_get_contents($file['tmp_name']));

  \store\set_profile_picture($type, $id, $picture['mime_type'], $picture['content']);
  \store\put_audit_log($table, $id, "Uploaded profile picture for $table/$id.", 'user', operation: 'update');
}
elseif($method == 'DELETE') {
  \store\delete_profile_picture($type, $id);
  \store\put_audit_log($table, $id, "Removed profile picture from $table/$id.", 'user', operation: 'update');
}
else {
  fail("Method not allowed.", status: 405);
}

include __DIR__ . "/detail.php";
