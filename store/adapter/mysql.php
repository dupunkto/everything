<?php
// Adapter for MySQL databases.

namespace adapter;
use PDO;

function establish_connection() {
  global $_DATABASE;

  $host = $_DATABASE['host'];
  $user = $_DATABASE['user'];
  $pass = $_DATABASE['pass'];
  $port = $_DATABASE['port'] ?? 3306;
  $name = ltrim($_DATABASE['path'], "/");

  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];

  $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
  $dbh = new PDO($dsn, $user, $pass, $options);

  register_functions($dbh);

  return $dbh;
}

function register_functions($dbh) {
  if(!function_defined($dbh, 'EXO_NORMALIZE')) $dbh->exec("CREATE FUNCTION EXO_NORMALIZE(input TEXT)
    RETURNS TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    DETERMINISTIC NO SQL
    RETURN LOWER(input)");
}

function function_defined($dbh, $name) {
  $query = $dbh->prepare("SELECT 1 FROM information_schema.routines
    WHERE routine_schema = DATABASE()
      AND routine_type = 'FUNCTION'
      AND routine_name = ?");
  $query->execute([$name]);
  return !!$query->fetch();
}

function initial_run() {
  return !table_exists('migrations');
}

function execute($path) {
  $sql = file_get_contents($path);
  $queries = explode(';', $sql);

  foreach ($queries as $query) {
    $query = trim($query);
    if(!empty($query)) DBH->exec($query);
  }
}

function table_exists($table_name) {
  $stmt = DBH->prepare("SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = ?");

  $stmt->execute([$table_name]);
  return $stmt->fetch() != false;
}