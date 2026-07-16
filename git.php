<?php
// Auto-detects if the current instance is running from a Git release.

$_SHA = resolve_git_head(__DIR__);
if($_SHA) define('GIT_SHA', $_SHA);

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
