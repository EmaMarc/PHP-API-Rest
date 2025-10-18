<?php
declare(strict_types=1);

namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';
require_once __DIR__ . '/../Utils/Authentication.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\Validation;
use PDO;


final class UsersModule {
  //Funciones para users

// GET /users?search={text} ------------------------------------------------------------------------
public static function searchUsers(Request $req, Response $res): Response {
    $search   = trim((string)($req->getQueryParams()['search'] ?? ''));
    // ahora si no se envía o viene vacío, devolver todos los users

    try { 
      $db = \DB::getConnection();

      //habilita el uso de LIKE para que sea la busqueda parcial
      // si no hay search, devuelvo todos los usuarios NO admin
      if ($search === '') {
        $sql = "SELECT id, email, first_name, last_name, is_admin
                FROM users
                WHERE is_admin = 0
                ORDER BY id DESC";
        $st  = $db->prepare($sql);
        $st->execute([]);
      } else {
        // Con filtro parcial por nombre, apellido o parte local del email
        $sql = "SELECT id, email, first_name, last_name, is_admin
                FROM users
                WHERE is_admin = 0
                  AND (
                    first_name LIKE ?
                    OR last_name LIKE ?
                    OR SUBSTRING_INDEX(email, '@', 1) LIKE ?
                  )
                ORDER BY id DESC";
        $st  = $db->prepare($sql);
        $pattern = '%' . $search . '%';
        // ahora con execute
        $st->execute([$pattern, $pattern, $pattern]);
      }

      // obtengo todas las filas que coincidean
      $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

      $res->getBody()->write(json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
      return $res->withHeader('Content-Type','application/json; charset=utf-8');

    } catch (\Throwable $e) {
      error_log($e);
      $res->getBody()->write(json_encode(['error' => 'Error interno']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
    }
}




  // GET /user/{id} ---------------------------------------------------------------------------------- 
  public static function getUserById(Request $req, Response $res, array $args): Response {
    $id = $args['id'] ?? 0;
    $auth = $req->getAttribute('auth_user'); // id y is_admin desde el token (validado por el middleware)
    $targetId = (int)$id;//id desde la ruta

    //Valido si la id no es un numero
    if (!is_numeric($id)) {
      // Si no es un número retorno un error 400
      $res->getBody()->write(json_encode(['error' => 'La ID debe ser un número']));
      return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400); // CAMBIO: charset
    }

    //pregunto si esta autorizado y le paso auth y id del usuario a modificar
    if (!\Authentication::isAuthorized($auth, $targetId)) {
      $res->getBody()->write(json_encode(['error' => 'No autorizado']));
      return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                  ->withStatus(401);
    }

    
    //casteo a int
    $id = (int)$id;
    //Valido si la id es menor o igual a 0
    if ($id <= 0) {
      $res->getBody()->write(json_encode(['error' => 'La ID no es válida']));
      return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400); // CAMBIO: charset
    }
    
    try {
      $db = \DB::getConnection();
      //ahora con prepare y execute
      $st = $db->prepare("SELECT id, email, first_name, last_name, is_admin FROM users WHERE id = ? LIMIT 1");
      $st->execute([$id]);
      $row = $st->fetch(\PDO::FETCH_ASSOC);

      //Si ninguna fila fue encontrada, retorno un error 404
      if (!$row) {
          $res->getBody()->write(json_encode(['error' => "Usuario no encontrado"]));
          return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(404); // CAMBIO: charset
      }

      //si el token es valido, refresco su expiración
      \Authentication::refreshToken($db, $auth['id'], 300);

      //Escribe en el body la respuesta en formato JSON
      $res->getBody()->write(json_encode($row));
      //Retorno la res por defecto 200
      return $res->withHeader('Content-Type', 'application/json; charset=utf-8'); // CAMBIO: charset

    } catch (\Throwable $e) { // CAMBIO
      $res->getBody()->write(json_encode(['error' => 'Error interno']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
    }
 }

  // POST /user --------------------------------------------------------------------------------------
  public static function createUser(Request $req, Response $res): Response {
    // ahora con body parsing middleware
    $data = $req->getParsedBody(); 

    $email     = (string)($data['email'] ?? '');
    $password  = (string)($data['password'] ?? '');
    $firstName = (string)($data['first_name'] ?? '');
    $lastName  = (string)($data['last_name'] ?? '');

    //valido que los campos obligatorios no estén vacíos
    // ahora los errores en un array
    $errors = [
      'email'      => [],
      'password'   => []
    ];

    if ($email === '')     { $errors['email'][] = 'El email es obligatorio'; }
    if ($password === '')  { $errors['password'][] = 'La contraseña es obligatoria'; }
    
    //valido que el email tenga un formato válido
    if ($email !== '' && !Validation::isValidEmail($email)) {
      $errors['email'][] = 'El email no tiene un formato válido';
    }

    // ahora uso validatePassword para devolver todos los errores
    if ($password !== '') {
      $pwdCheck = Validation::validatePassword($password);
      if (!$pwdCheck['ok']) {
        foreach ($pwdCheck['errors'] as $msg) {
          $errors['password'][] = $msg;
        }
      }
    }

    // si hay cualquier error devolve el array de errores por campo
    $hasErrors = array_reduce($errors, fn($carry, $arr) => $carry || !empty($arr), false);
    if ($hasErrors) {
      $res->getBody()->write(json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')
                  ->withStatus(400);
    }

    try {
      $db = \DB::getConnection();

      //verifico que no exista otro usuario con el mismo email
      // ahora con prepare y execute
      $st = $db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
      $st->execute([$email]);
      if ($st->fetchColumn()) {
        // Devolver arreglo de errores por campo 
        $res->getBody()->write(json_encode(['errors' => ['email' => ['Ya existe un usuario con ese email']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')
                   ->withStatus(409); // 409 Conflicto
      }

      // ahora hasheo password
      $hash = \Authentication::hashPassword($password);
      if ($hash === '') {
        $res->getBody()->write(json_encode(['error' => 'No se pudo procesar la contraseña']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
      }

      //Inserto el nuevo usuario en la base de datos
      //ahora con prepare y execute
      $ins = $db->prepare('
        INSERT INTO users (email, password, first_name, last_name, is_admin)
        VALUES (?, ?, ?, ?, 0)
      ');
      $ok = $ins->execute([$email, $hash, $firstName, $lastName]);

      // si falla 
      if ($ok === false) {
        $res->getBody()->write(json_encode(['error' => 'Error al insertar usuario']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
      }

      // devuelvo el creado
      //ahora con prepare y execute
      $id = (int)$db->lastInsertId();
      $sel = $db->prepare('SELECT id, email, first_name, last_name, is_admin FROM users WHERE id = ?');
      $sel->execute([$id]);
      $row = $sel->fetch(\PDO::FETCH_ASSOC);

      $res->getBody()->write(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')
                  ->withStatus(201);//201 Credao

    } catch (\Throwable $e) {
      error_log($e);
      $res->getBody()->write(json_encode(['error' => 'Error interno']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
    }
 }


  // PATCH /user/{id} --------------------------------------------------------------------------------
  public static function updateUser(Request $req, Response $res, array $args): Response{
    $targetId = (int)($args['id']);//id desde la ruta
    $auth = $req->getAttribute('auth_user'); // id y is_admin desde el token (validado por el middleware)

    //pregunto si esta autorizado y le paso auth y id del usuario a modificar
    if (!\Authentication::isAuthorized($auth, $targetId)) {
      $res->getBody()->write(json_encode(['error' => 'No autorizado']));
      return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                  ->withStatus(401);
    }
    //obtengo los datos del body
    //ahora con body parsing middleware
    $data = $req->getParsedBody();

    //extraigo los campos (si no vienen, quedan en null)
    $email     = array_key_exists('email', $data) ? (string)$data['email'] : null; 
    $password  = array_key_exists('password', $data) ? (string)$data['password'] : null; 
    $firstName = array_key_exists('first_name', $data) ? (string)$data['first_name'] : null; 
    $lastName  = array_key_exists('last_name', $data) ? (string)$data['last_name'] : null; 

    // CAMBIO: juntamos errores por campo (como pidió la cátedra)
    $errors = [
      'email'      => [],
      'password'   => []
    ];


    //valido si el mail es correcto
    if ($email !== null) {
      if ($email !== '' && !Validation::isValidEmail($email)) {
        $errors['email'][] = 'El email no tiene un formato válido';
      }
      if ($email === '') {
        $errors['email'][] = 'El email no puede estar vacío';
      }
    }

    //valido la password es valida
    if ($password !== null) {
        // ahora usa validatePassword para devolver todos los mensajes
        if ($password === '') {
          $errors['password'][] = 'La contraseña no puede estar vacía';
        } else {
          $pwdCheck = Validation::validatePassword($password);
          if (!$pwdCheck['ok']) {
            foreach ($pwdCheck['errors'] as $msg) {
              $errors['password'][] = $msg;
            }
          }
        }
    }

    // si hay cualquier error devolve el array de errores
    $hasErrors = array_reduce($errors, fn($carry, $arr) => $carry || !empty($arr), false);
    if ($hasErrors) {
      $res->getBody()->write(json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

    try {
      $db   = \DB::getConnection();

      //busco el usuario a modificar con select
      //ahora con prepare y execute
      $sel = $db->prepare("SELECT id, email, first_name, last_name, is_admin FROM users WHERE id = ? LIMIT 1");
      $sel->execute([$targetId]);
      $row = $sel->fetch(\PDO::FETCH_ASSOC);
      //si no existe, retorno error 404
      if (!$row) {
        $res->getBody()->write(json_encode(['error' => 'Usuario no encontrado']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(404);
      }


      //si el email viene y es distinto al actual, verifico que no exista otro usuario con ese email
      if ($email !== null && $email !== $row['email']) {
        //ahora con prepare y execute
        $dup = $db->prepare("SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $dup->execute([$email, $targetId]);
        $duplicate = $dup->fetchColumn();

        //si existe, retorno error 409
        if ($duplicate) {
          $res->getBody()->write(json_encode(['errors' => ['email' => ['Ya existe un usuario con ese email']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(409);
        }
      }

      //armo el SET porque pueden venir uno o más campos a modificar
      $sets = [];

      //agrego cada campo al set
      if ($email !== null) { $sets['email'] = $email; }
      if ($password !== null) { 
          //ahora hasheo la nueva pass antes de guardar
          $hash = \Authentication::hashPassword($password);
          if ($hash === '') {
            $res->getBody()->write(json_encode(['error' => 'No se pudo hashear la password']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
          }
          $sets['password'] = $hash;
      }
      if ($firstName !== null) { $sets['first_name'] = $firstName; }
      if ($lastName !== null)  { $sets['last_name']  = $lastName; }

      //si no hay campos para actualizar, retorno error 400
      if (empty($sets)) {
        $res->getBody()->write(json_encode(['error' => 'El json no puede estar vacio']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
      }

      //$columns es para guardar las columnas a actualizar
      $columns = [];
      // $params es para guardar los parámetros de la consulta
      $params  = [];

      //para cada uno de los campos a modificar
      //guardo solo lo que vino del body
      foreach ($sets as $col => $val) {
        $columns[] = "$col = :$col";
        $params[":$col"] = $val;
      }
      //agrego el id la consulta
      $params[':id'] = $targetId;

      //armo la consulta de actualización
      $sql = "UPDATE users SET " . implode(', ', $columns) . " WHERE id = :id LIMIT 1";
      //ahora con prepare y execute
      $up  = $db->prepare($sql);
      $ok  = $up->execute($params);

      if ($ok === false) {
        $res->getBody()->write(json_encode(['error' => 'Error al actualizar el usuario']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
      }

      // devuelvo el seleccionado modificado
      $sel2 = $db->prepare("SELECT id, email, first_name, last_name, is_admin FROM users WHERE id = ? LIMIT 1");
      $sel2->execute([$targetId]);
      $row = $sel2->fetch(\PDO::FETCH_ASSOC);

      //si el token es valido, refresco su expiración
      \Authentication::refreshToken($db, $auth['id'], 300);

      $res->getBody()->write(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      return $res->withHeader('Content-Type','application/json; charset=utf-8');

    } catch (\Throwable $e) {
      error_log($e);
      $res->getBody()->write(json_encode(['error' => 'Error interno']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
    }
  }




  // DELETE /user/{id} -------------------------------------------------------------------------------
public static function deleteUser(Request $req, Response $res, array $args): Response {
    $targetId = (int)($args['id']);//id desde la ruta
    
    //Valido si la id es menor o igual a 0
    if ($targetId <= 0) {
      $res->getBody()->write(json_encode(['error' => 'ID inválido']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

    $auth = $req->getAttribute('auth_user'); // id y is_admin desde el token (validado por el middleware)

    //pregunto si esta autorizado y le paso auth y id del usuario a modificar
    if (!\Authentication::isAuthorized($auth, $targetId)) {
      $res->getBody()->write(json_encode(['error' => 'No autorizado']));
      return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                  ->withStatus(401);
    }

    try {
      $db = \DB::getConnection();

      //si el token es valido, refresco su expiración
      \Authentication::refreshToken($db, $auth['id'], 300);

      //busco el usuario a borrar
      // CAMBIO: usar prepare + execute
      $sel = $db->prepare("SELECT id, is_admin FROM users WHERE id = ? LIMIT 1");
      $sel->execute([$targetId]);
      $row = $sel->fetch(\PDO::FETCH_ASSOC);

      //si no existe, retorno error 404
      if (!$row) {
          $res->getBody()->write(json_encode(['error' => 'Usuario no encontrado']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')
                      ->withStatus(404);
      }

      //si es admin, retorno error 400
      if ((int)$row['is_admin'] === 1) {
          $res->getBody()->write(json_encode(['error' => 'No se puede eliminar un usuario administrador']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')
                      ->withStatus(400);
      }

      //Verifico si el usuario tiene reservas
      $hasBookingsOwn = $db->prepare("SELECT 1 FROM bookings WHERE created_by = ? LIMIT 1");
      $hasBookingsOwn->execute([$targetId]);
      $own = (bool)$hasBookingsOwn->fetchColumn();

      $hasBookingsAsParticipant = $db->prepare("SELECT 1 FROM booking_participants WHERE user_id = ? LIMIT 1");
      $hasBookingsAsParticipant->execute([$targetId]);
      $asParticipant = (bool)$hasBookingsAsParticipant->fetchColumn();

      if ($own || $asParticipant) {
        $res->getBody()->write(json_encode(['error' => 'El usuario posee reservas (como creador o participante), no se puede eliminar']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(409);
      }

      //si no tiene reservas, lo borro
      //ahora con prepare + execute
      $del = $db->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
      $ok  = $del->execute([$targetId]);

      //chequeo de ejecución
      if ($ok === false || $del->rowCount() === 0) {
        $res->getBody()->write(json_encode(['error' => 'No se pudo eliminar el usuario']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
      }

      $res->getBody()->write(json_encode(['ok' => true, 'deleted_id' => $targetId]));
      return $res->withHeader('Content-Type','application/json; charset=utf-8');

    } catch (\Throwable $e) {
      $res->getBody()->write(json_encode(['error' => 'Error interno']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
    }
}


}