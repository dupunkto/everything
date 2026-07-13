<?php

  if(!isset($item)) {
    $kind = @$_GET['kind'] ?? "person";
    $id = @$_GET['id'] ?? @$_POST['id'];

    if(!in_array($kind, ['person', 'org'])) 
      fail("Malformed 'kind' parameter.", status: 400);

    if($id && isset($_POST['note'])) {
      if($kind == "org") \store\update_organisation_note($id, $_POST['note'])
        or fail("Could not save note.");

      if($kind == "person") \store\update_contact_note($id, $_POST['note'])
        or fail("Could not save note.");
    }

    $item = ['id' => $id, 'note' => @$_POST['note']];
  }

?>
<form class="detail__note" x-post="/contacts/note" x-on="change" x-replace="outerHTML">
  <h3>Note</h3>
  <input type="hidden" name="kind" value="<?= $kind ?>">
  <input type="hidden" name="id" value="<?= esc_attr($item['id']) ?>">
  <textarea name="note" rows="4" placeholder="Anything to note..?"><?= esc_inner($item['note'] ?? '') ?></textarea>
</form>
