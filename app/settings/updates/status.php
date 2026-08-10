<?php

if(!defined('GIT_SHA')) fail("Updates are only available when running from a Git clone.", status: 404);

$action = @$_POST['action'];

if($action == 'check') {
  [$exit, $error] = git('fetch');
  if($exit) fail("Could not fetch updates: $error", status: 409);
}

if($action == 'install') {
  if(!git_worktree_is_clean()) fail("The update cannot be installed while the working tree has changes.", status: 409);
  $installed = git_upstream_update_count();
  if(!$installed) fail("There are no updates to install.", status: 409);

  [$exit, $error] = git('pull');
  if($exit) fail("Could not install the update: $error", status: 409);
}

$updates = git_upstream_update_count();

?>
<?php if(@$installed): ?>
  <p class="success"><?= $installed == 1 ? "Update" : "Updates" ?> installed.</p>
<?php elseif($updates): ?>
  <p class="info">
    <?php if($updates == 1): ?>
      An update is available.
    <?php else: ?>
      There are <?= esc_inner($updates) ?> updates available.
    <?php endif ?>
  </p>
  <form x-post="/settings/updates/status" x-target="#updates-status">
    <button type="submit" name="action" value="install">Download and install update</button>
  </form>
<?php elseif($action == 'check'): ?>
  <p class="success">Everything is up to date.</p>
<?php endif ?>
