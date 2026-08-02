#!/usr/bin/env php
<?php
// Manual Git export maintenance: initialization, recovery and verification.
//
//   php export.php initialize [repository]
//   php export.php recover    [repository]
//   php export.php verify     [repository]

if(php_sapi_name() != 'cli') die("This program is CLI-only.\n");

$_ENV = getenv("ENV") ?: 'prod';

require_once __DIR__ . "/core.php";

function say($message) { fwrite(STDOUT, "$message\n"); }
function bail($message) { fwrite(STDERR, "$message\n"); exit(1); }

$command = @$argv[1];
$repository = @$argv[2] ?? \export\repository();

if(!is_str($repository) && $command !== null)
  bail("No repository given; pass a path or configure developer.git-repository.");

try {
  switch($command) {
    case 'initialize':
      \export\create_repository($repository);
      \export\validate($repository);
      \export\lock($repository);
      if(\export\initialized($repository)) bail("The repository is already initialized.");
      \export\initialize_baseline($repository);
      say("Baseline committed, export initialized.");
      break;

    case 'recover':
      \export\validate($repository);
      \export\lock($repository);

      $pending = \export\read_pending($repository);

      if(!$pending) {
        if(!\export\worktree_clean($repository))
          bail("The worktree is dirty but no export is pending, refusing to guess.");

        say("Nothing to recover.");
        break;
      }

      $committed = \export\commit_state($repository, "{$pending['method']} {$pending['path']}");

      \export\clear_pending($repository);

      say($committed ? "Recovered, pending changes are committed." : "Recovered, no changes were left to commit.");
      break;

    case 'verify':
      \export\validate($repository);
      \export\lock($repository);

      $differences = \export\verify($repository);

      if(!$differences) { say("The export repository matches the database."); break; }

      foreach($differences as $difference) say($difference);

      exit(1);

    default:
      say("Usage: php export.php <initialize|recover|verify> [repository]");
      exit(64);
  }
}
catch(Throwable $error) {
  bail($error->getMessage());
}
