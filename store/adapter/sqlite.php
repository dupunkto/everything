<?php
// Adapter for SQLite databases.

namespace adapter;
use PDO;

function establish_connection() {
  global $_DATABASE;

  $database = $_DATABASE['path'];
  $dsn = "sqlite:$database";

  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  try {
    define('INITIAL_RUN', !file_exists($database));
    $dbh = new PDO($dsn, options: $options);

    // SQLite ignores foreign keys (and their ON DELETE actions) unless
    // enforcement is switched on per connection.
    $dbh->exec("PRAGMA foreign_keys = ON");

    return $dbh;
  }
  catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
  }
}

// Other adapters use a runtime check on the database schema to
// determine whether this is the initial run. However, the SQLite
// adapter can simply check the existance of the database on
// define 'INITIAL_RUN' accordingly, at connection time. Therefore,
// this function is unused right now. This implementation is purely
// for 'what if'-purposes and to ensure API compatibility with other
// database adapters.
function initial_run() {
  return INITIAL_RUN;
}

function execute($path) {
  $queries = explode(';', file_get_contents($path));

  foreach ($queries as $query) {
    $query = trim($query);
    if(empty($query)) continue;

    if(str_contains($query, "AUTO_INCREMENT"))
      $query = str_replace(",\n  PRIMARY KEY (`id`)", "", $query);

    $query = str_replace(
      ["int(11)", "NOT NULL AUTO_INCREMENT"],
      ["INTEGER", "PRIMARY KEY AUTOINCREMENT"],
      $query
    );

    DBH->exec($query) !== false
      or die("Could not execute query '$query'.");
  }
}

function table_exists($table_name) {
  $stmt = DBH->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
  $stmt->execute([$table_name]);
  return $stmt->fetch() != false;
}
