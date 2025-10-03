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

  /**
   * Regla de autorización centralizada.
   * $permission: nombre de la acción (p.ej. 'user.edit')
   * $ctx: contexto adicional (p.ej. ['userId' => 123])
  */
  //$auth = id y is admin, $permission la accion a verificar, $ctx contexto adicional
  public static function isAuthorized(array $auth, string $permission, array $ctx = []): bool {
    switch ($permission) {
      case 'user.edit':
        return (int)($auth['is_admin'] ?? 0) === 1
            || (int)($auth['id'] ?? 0) === (int)($ctx['userId'] ?? 0);

      // Más adelante podés sumar reglas:
      // case 'booking.create': ...
      // case 'booking.cancel': ...
      default:
        return false; // por defecto, no autorizado
    }

  }

}
