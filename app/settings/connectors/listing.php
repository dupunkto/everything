<?php

$labels = [
  'notes' => "Notes",
  'todo' => "ToDo",
  'bookmarks' => "Bookmarks",
  'calendar' => "Calendar",
  'contacts' => "Contacts and addresses",
];

?>
<ul class="settings-listing settings-listing--connectors">
  <?php foreach(\store\list_connectors() as $connector): ?>
    <?php $apps = array_column(\store\list_connector_apps($connector['id']), 'app') ?>
    <li class="connector-editor">
      <form x-post="/settings/connectors/edit" x-on="change" x-target="#connectors-listing">
        <div class="connector-editor__header">
          <input name="id" type="hidden" value="<?= esc_attr($connector['id']) ?>">
          <input name="name" type="text" required value="<?= esc_attr($connector['name']) ?>" aria-label="Connector name">
          <button type="button" x-post="/settings/connectors/cycle?id=<?= esc_attr($connector['id']) ?>" x-target="#connectors-listing" z-confirm="Cycle this token? The current URL will stop working.">Cycle token</button>
          <button type="button" x-delete="/settings/connectors/delete?id=<?= esc_attr($connector['id']) ?>" x-target="#connectors-listing" z-confirm="Delete this connector?">&times;</button>
        </div>
        <input class="connector-editor__url" type="url" readonly value="<?= esc_attr(CANONICAL . "/mcp/{$connector['token']}") ?>" aria-label="MCP server URL">
        <fieldset class="connector-editor__apps">
          <legend>Permissions</legend>
          <?php foreach($labels as $app => $label): ?>
            <label><input name="app[]" type="checkbox" value="<?= esc_attr($app) ?>" <?= in_array($app, $apps) ? 'checked' : '' ?>> <?= esc_inner($label) ?></label>
          <?php endforeach ?>
        </fieldset>
      </form>
    </li>
  <?php endforeach ?>
</ul>
