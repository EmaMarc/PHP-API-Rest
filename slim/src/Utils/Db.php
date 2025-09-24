<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;

final class Db {
  private static ?PDO $pdo = null;
  public static function pdo(): PDO {
    if (!self::$pdo) {
      $host = getenv('DB_HOST') ?: 'mysql';
      $port = (int)(getenv('DB_PORT') ?: 3306);
      $db   = getenv('DB_NAME') ?: 'seminariophp';
      $user = getenv('DB_USER') ?: 'root';
      $pass = getenv('DB_PASS') ?: 'root';
      $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
      self::$pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    }
    return self::$pdo;
  }
}
