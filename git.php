<?php
// Auto-detects if the current instance is running from a Git release.

$_SHA = resolve_git_head(__DIR__);
if($_SHA) define('GIT_SHA', $_SHA);

function git(...$args) {
  return git_c(__DIR__, ...$args);
}

function git_c($directory, ...$args) {
  $command = array_merge(["git", "-C", $directory], $args);
  $process = proc_open($command, [
    1 => ['pipe', 'w'],
    2 => ['redirect', 1],
  ], $pipes);

  if(!is_resource($process)) return [1, "Could not start Git."];

  $output = stream_get_contents($pipes[1]);
  fclose($pipes[1]);

  return [proc_close($process), trim($output)];
}

function git_upstream_has_updates() {
  [$exit, $count] = git('rev-list', '--count', 'HEAD..@{u}');
  if($exit) fail("Could not compare with the upstream branch: $count", status: 409);
  return (int)$count > 0;
}

function git_worktree_is_clean() {
  [$exit, $status] = git('status', '--porcelain');
  if($exit) fail("Could not inspect the working tree: $status", status: 409);
  return $status == '';
}

function resolve_git_head($directory) {
  $git = "$directory/.git";
  $head = @file_get_contents("$git/HEAD");

  if(!$head) return null;

  $head = trim($head);

  if(!str_starts_with($head, "ref: ")) return $head; // detached HEAD

  $ref = substr($head, 5);
  $sha = @file_get_contents("$git/$ref");

  if($sha !== false) return trim($sha);

  // Loose ref is absent; the ref is packed.
  foreach(@file("$git/packed-refs", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if($line[0] == '#' || $line[0] == '^') continue;
    [$sha, $name] = explode(' ', $line, 2);
    if($name == $ref) return $sha;
  }
  return null;
}
