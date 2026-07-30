<?php

if(!defined('GIT_SHA')) fail("Updates are only available when running from a Git clone.", status: 404);

$action = @$_POST['action'];

if($action == 'check') {
  [$exit, $error] = git('fetch');
  if($exit) fail("Could not fetch updates: $error", status: 409);
}

if($action == 'install') {
  if(!git_worktree_is_clean()) fail("The update cannot be installed while the working tree has changes.", status: 409);
  if(!git_upstream_has_updates()) fail("There are no updates to install.", status: 409);

  [$exit, $error] = git('pull');
  if($exit) fail("Could not install the update: $error", status: 409);
  $installed = true;
}

$updates = git_upstream_has_updates();

?>
<?php if(@$installed): ?>
  <p class="success">Update installed.</p>
<?php elseif(@$updates): ?>
  <p class="info">An update is available.</p>
  <form x-post="/settings/updates/status" x-target="#updates-status">
    <button type="submit" name="action" value="install">Download and install update</button>
  </form>
<?php elseif($action == 'check'): ?>
  <p class="success">Everything is up to date.</p>
<?php endif ?>

<?php if(!@$updates): ?>
  <form x-post="/settings/updates/status" x-target="#updates-status">
    <button type="submit" name="action" value="check">Check for updates</button>
  </form>
<?php endif ?>
