<?php
  // Configures git export.

  // Enable and disable are the explicit lifecycle actions for Git export.
  // Enabling starts while export is still disabled, so the action itself
  // locks the repository and creates the baseline before storing config;
  // disabling is an ordinary exported mutation that commits its own state.

  if(isset($_POST['enable'])) {
    $repository = cast_str(@$_POST['repository'])
      or fail("Provide the path to the export repository.", status: 422);

    if(!\export\enabled()) {
      \export\activate($repository, 'POST', '/settings/developer/git/edit');
      \store\update_config('developer.git-repository', $repository);
      \store\update_config('developer.git-enabled', 'true');
      \store\put_audit_log('config', 'developer', "Enabled Git export.", 'user');
    }
    elseif($repository != \export\repository()) {
      fail("Disable Git export before changing the repository.", status: 409);
    }
  }

  if(isset($_POST['disable']) && \export\enabled()) {
    \store\update_config('developer.git-enabled', 'false');
    \store\put_audit_log('config', 'developer', "Disabled Git export.", 'user');
  }

  $enabled = cast_bool(\config\canonical_value('developer.git-enabled'));
  $repository = \config\canonical_value('developer.git-repository');
  $valid = is_str($repository) && is_dir($repository) && \export\git_dir($repository);
  $initialized = $valid && \export\initialized($repository);
  $pending = $valid ? \export\read_pending($repository) : null;
  $clean = $valid ? \export\worktree_clean($repository) : null;

?>
<form class="settings-form settings-form--spaced" x-post="/settings/developer/git/edit" x-target="#git-settings">
  <label>
    Repository path
    <input name="repository" type="text" placeholder="/var/lib/everything-export"
      value="<?= esc_attr($repository ?? '') ?>" <?= $enabled ? 'readonly' : '' ?>>
  </label>

  <?php if($enabled): ?>
    <button type="submit" name="disable" value="1"
      z-confirm="Disable Git export? Later changes will no longer be committed.">Disable Git export</button>
  <?php else: ?>
    <button type="submit" name="enable" value="1">Enable Git export</button>
  <?php endif ?>
</form>

<ul class="settings-menu">
  <li>Export is <strong><?= $enabled ? "enabled" : "disabled" ?></strong>.</li>

  <?php if($repository === null): ?>
    <li>No repository is configured<?= $enabled ? "; mutations are blocked until one is" : "" ?>.</li>
  <?php elseif(!$valid): ?>
    <li>The configured path is not an initialized Git worktree<?= $enabled ? "; mutations are blocked" : "" ?>.</li>
  <?php else: ?>
    <li>The repository <?= $initialized
      ? "holds an export baseline"
      : "has no baseline yet" . ($enabled ? "; mutations are blocked until one exists" : "") ?>.</li>

    <?php if($pending && !\export\active()): ?>
      <li>A previous export did not complete (<?= esc_inner("{$pending['method']} {$pending['path']}") ?>,
        started <?= esc_inner($pending['started_at']) ?>). The next mutation retries it.</li>
    <?php elseif($clean === false): ?>
      <li>The worktree has uncommitted changes; mutations are blocked until it is clean.</li>
    <?php endif ?>
  <?php endif ?>
</ul>
