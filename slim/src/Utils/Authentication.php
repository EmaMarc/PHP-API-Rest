<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Utils/db.php';

final class Authentication {
  public static function makeToken(\PDO $db, int $userId, int $ttlSeconds = 300): ?string {
    //uniqid genera un id unico. Y md5 lo convierte en 32
    $token = md5(uniqid());
    //expired = ahora + ttlSeconds(5mins)
    $expired = date('Y-m-d H:i:s', time() + $ttlSeconds);

    //actualizo el token y expired en la base de datos
    $sql = "UPDATE users
            SET token = " . $db->quote($token) . ",
            expired = " . $db->quote($expired) . "
            WHERE id = $userId";

    //ejecuto la consulta, si no se pudo ejecutar, devuelvo null
    $ok = $db->exec($sql);
    //si se actualizo al menos una fila, devuelvo el token, sino null
    return ($ok > 0) ? $token : null;
  }

  public static function verifyPassword(string $plain, string $stored): bool {
    return $plain === $stored; 
  }


  public static function isAuthorized(array $auth, int $targetUserId): bool {
    // Admin
    if ((int)($auth['is_admin'] ?? 0) === 1) {
        return true;
    }
    // Dueño del recurso
    return (int)($auth['id'] ?? 0) === $targetUserId; 
  }

}
