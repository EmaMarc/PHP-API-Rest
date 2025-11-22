<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Utils/db.php';

final class Authentication {
  public static function makeToken(\PDO $db, int $userId, int $ttl = 300): ?string {
    // Genero el token y le hago trim por las dudas
    $token = trim(md5(uniqid()));

    // Genero fecha de expiración y también trim
    $expired = trim(date('Y-m-d H:i:s', time() + $ttl));

    // Consulta con TRIM() aplicado dentro del SQL
    $sql = "UPDATE users
            SET token = TRIM(?), 
                expired = TRIM(?)
            WHERE id = ?";

    $stmt = $db->prepare($sql);

    // Orden exacto de los parámetros
    $ok = $stmt->execute([
        $token,   // TRIM(?) → token limpio
        $expired, // TRIM(?) → fecha limpia
        $userId   // id
    ]);

    return ($ok && $stmt->rowCount() > 0) ? $token : null;
}


  // Solo refresca la expiración usando la hora de la BD
  public static function refreshToken(\PDO $db, int $userId, int $ttlSeconds = 300): bool {
    $db = \DB::getConnection();
    //Lo extiendo 300 segs
    $sql = "UPDATE users
            SET expired = DATE_ADD(NOW(), INTERVAL {$ttlSeconds} SECOND)
            WHERE id = {$userId}";
    return $db->exec($sql) > 0;
  }


  public static function isAuthorized(array $auth, int $targetUserId): bool {
    // Admin
    if ((int)($auth['is_admin'] ?? 0) === 1) {
        return true;
    }
    // Dueño del recurso
    return (int)($auth['id'] ?? 0) === $targetUserId; 
  }

  public static function tienePermiso(array $auth, int $id): bool {
    $id_actual = (int)($auth['id'] ?? 0);
    $is_admin = (int)($auth['is_admin'] ?? 0);

    //devuelve true si es admin o si el id actual es igual al id pasado
    return $is_admin === 1 || $id_actual === $id;
  }

  public static function isAdmin(array $auth): bool {
    // Admin
    return (int)($auth['is_admin'] ?? 0) === 1;
  }


  public static function hashPassword(string $plainPassword): string {
    // Si la contraseña viene vacía, devuelvo cadena vacía para evitar errores
    if (trim($plainPassword) === '') return '';

    // Hashe simple y efectivo
    // password_hash usa el algoritmo BCRYPT por defecto, seguro y práctico
    return password_hash($plainPassword, PASSWORD_DEFAULT);
}

  // Verifica si la contraseña en texto plano coincide con el hash almacenado
  public static function verifyPassword(string $plainPassword, string $hashedPassword): bool {
    //password_verify compara la contraseña en texto plano con el hash
    return password_verify($plainPassword, $hashedPassword);
}

}


