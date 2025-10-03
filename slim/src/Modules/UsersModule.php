<?php
declare(strict_types=1);

namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';
require_once __DIR__ . '/../Utils/Authentication.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;


final class UsersModule {
  //Funciones para users
  
  // GET /users ---------------------------------------------------------------------------------------
  public static function getAllUsers(Request $req, Response $res): Response {
    $db = \DB::getConnection();

    //sql = Consulta SQL para obtener todos los usuarios
    $sql = "SELECT id, email, password, first_name, last_name, is_admin
            FROM users
            ORDER BY id DESC";

    //Ejecutar la consulta $sql y guardo el resultado en $rows
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    //Escribe en el body la respuesta en formato JSON
    $res->getBody()->write(json_encode($rows, JSON_UNESCAPED_UNICODE));

    //Retorno la res 200 por defecto
    return $res->withHeader('Content-Type','application/json');
  }

  // GET /users?search={text} ------------------------------------------------------------------------
  public static function searchUsers(Request $req, Response $res): Response {
    $search   = trim((string)$req->getQueryParams()['search']);
    
    //Si el parámetro search está vacío, retorno un error 400 
    if ($search === '') {
        $res->getBody()->write(json_encode(['error' => 'El parámetro "search" no puede estar vacío']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')
        ->withStatus(400);
    }

    $db = \DB::getConnection();

    //habilita el uso de LIKE para que sea la busqueda parcial
    $patternQ = $db->quote('%' . $search . '%');

    $sql = "SELECT id, email, first_name, last_name, is_admin
            FROM users
            WHERE is_admin = 0
            AND (first_name LIKE $patternQ
              OR last_name  LIKE $patternQ
              OR SUBSTRING_INDEX(email, '@', 1) 
              LIKE $patternQ)
            ORDER BY id DESC";

    $rows = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

    $res->getBody()->write(json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    return $res->withHeader('Content-Type','application/json; charset=utf-8');
  }



  // GET /user/{id} ---------------------------------------------------------------------------------- 
  public static function getUserById(Request $req, Response $res, array $args): Response {
    $id = $args['id'] ?? 0;

    //Valido si la id no es un numero
    if (!is_numeric($id)) {
      // Si no es un número retorno un error 400
      $res->getBody()->write(json_encode(['error' => 'La ID debe ser un número']));
      return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    
    //casteo a int
    $id = (int)$id;
    //Valido si la id es menor o igual a 0
    if ($id <= 0) {
      //return $res->withStatus(400)->write('Invalid user ID');
      $res->getBody()->write(json_encode(['error' => 'La ID no es válida']));
      return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
    

    $db = \DB::getConnection();
    //sql = Consulta SQL para obtener el usuario por ID
    $sql = "SELECT id, email, first_name, last_name, is_admin
            FROM users
            WHERE id = $id
            LIMIT 1";
    
    //Ejecutar la consulta $sql y guardo el resultado en $row
    $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

    //Si ninguna fila fue encontrada, retorno un error 404
    if (!$row) {
        $res->getBody()->write(json_encode(['error' => "Usuario no encontrado"]));
        return $res->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    //Escribe en el body la respuesta en formato JSON
    $res->getBody()->write(json_encode($row));
    //Retorno la res por defecto 200
    return $res->withHeader('Content-Type', 'application/json');
  }

  // POST /user --------------------------------------------------------------------------------------
  public static function createUser(Request $req, Response $res): Response {
    $data = json_decode((string)$req->getBody(), true);

    $email = (string)$data['email'];
    $password = (string)$data['password'];
    $firstName = (string)($data['first_name'] ?? '');
    $lastName = (string)($data['last_name']  ?? '');

    //valido que los campos obligatorios no estén vacíos
    if ($email === '' || $password === '') {
      $res->getBody()->write(json_encode(['error' => 'Los campos email y password son obligatorios']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')
              ->withStatus(400);
    }

    //valido que el email tenga un formato válido
    $email_REGEX = '/^[\w\.-]+@[\w\.-]+\.\w{2,4}$/';
    if (!preg_match($email_REGEX, $email)) {
      $res->getBody()->write(json_encode(['error' => 'Email inválido']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')
                  ->withStatus(400);
    }

    //valido la password con mínimo 8 caracteres, una mayúscula, una minúscula, un número y un carácter especial
    $ok_password = strlen($password) >= 8
                      && preg_match('/[a-z]/', $password)      //minúscula
                      && preg_match('/[A-Z]/', $password)      //mayúscula
                      && preg_match('/\d/', $password)         //número
                      && preg_match('/[^\w\s]/', $password);   //carácter especial (NO espacio)
    if (!$ok_password) {
      $res->getBody()->write(json_encode(['error' => 'Contraseña invalida.']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

    $db = \DB::getConnection();

    //quoteo las variables para prevenir SQL Injection
    $emailQ = $db->quote($email);
    $passQ = $db->quote($password); 
    $fnQ = $db->quote($firstName);
    $lnQ = $db->quote($lastName);

    
    //Valido que no exista otro usuario con el mismo email
    $sql = "SELECT 1 FROM users WHERE email = $emailQ LIMIT 1";
    $row = $db->query($sql)->fetch(\PDO::FETCH_ASSOC);
    if ($row) {
      $res->getBody()->write(json_encode(['error' => 'Ya existe un usuario con ese email']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')
             ->withStatus(409); // 409 Conflicto
    }

    //Inserto el nuevo usuario en la base de datos
    $sql = "INSERT INTO users (email, password, first_name, last_name, is_admin)
            VALUES ($emailQ, $passQ, $fnQ, $lnQ, 0)";
    $db->exec($sql);

    // devuelvo el creado
    $id  = (int)$db->lastInsertId();
    $row = $db->query("SELECT id, email, first_name, last_name, is_admin 
                      FROM users WHERE id = $id")
              ->fetch(\PDO::FETCH_ASSOC);

    $res->getBody()->write(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $res->withHeader('Content-Type','application/json; charset=utf-8')
            ->withStatus(201);//201 Credao
  }

  // PATCH /user/{id} --------------------------------------------------------------------------------
  public static function updateUser(Request $req, Response $res, array $args): Response{
    $targetId = (int)($args['id']);//id desde la ruta
    $auth = $req->getAttribute('auth_user'); // id y is_admin desde el token (validado por el middleware)

    //pregunto si esta autorizado y le paso auth, la accion a realizar y id del usuario a modificar
    if (!\Authentication::isAuthorized($auth, 'user.edit', ['userId' => $targetId])) {
        $res->getBody()->write(json_encode(['error' => 'No autorizado']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')
                    ->withStatus(401);
    }

    //obtengo los datos del body
    $data = json_decode((string)$req->getBody(), true);

    //extraigo los campos (si no vienen, quedan en null)
    $email = array_key_exists('email', $data)      ? (string)$data['email'] : null;
    $password = array_key_exists('password', $data)   ? (string)$data['password'] : null;
    $firstName = array_key_exists('first_name', $data) ? (string)$data['first_name'] : null;
    $lastName = array_key_exists('last_name', $data)  ? (string)$data['last_name'] : null;

    // si vino first_name pero está vacío
    if ($firstName !== null && $firstName === '') {
      $res->getBody()->write(json_encode(['error' => 'El nombre no puede estar vacío']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

    // si vino last_name pero está vacío
    if ($lastName !== null && $lastName === '') {
      $res->getBody()->write(json_encode(['error' => 'El apellido no puede estar vacío']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

    //valido si el mail es correcto
    if ($email !== null) {
      if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/i', $email)) {
          $res->getBody()->write(json_encode(['error' => 'Email inválido']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
      }
    }
    //valido la password es valida
    if ($password !== null) {
        $ok = strlen($password) >= 8
          && preg_match('/[a-z]/', $password)
          && preg_match('/[A-Z]/', $password)
          && preg_match('/\d/', $password)
          && preg_match('/[^\w\s]/', $password); // especial, NO espacio
        if (!$ok) {
            $res->getBody()->write(json_encode(['error' => 'Contraseña inválida']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
        }
    }


    $db   = \DB::getConnection();

    //si el usuario no existe, retorno error 404
    $row = $db->query("SELECT id, email, first_name, last_name, is_admin FROM users WHERE id = $targetId")
          ->fetch(\PDO::FETCH_ASSOC);
    if (!$row) {
      $res->getBody()->write(json_encode(['error' => 'Usuario no encontrado']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(404);
    }
    //si el email viene y es distinto al actual, verifico que no exista otro usuario con ese email
    if ($email !== null && $email !== $row['email']) {
      $emailQ = $db->quote($email);
      $duplicate = $db->query("SELECT 1 FROM users WHERE email = $emailQ AND id <> $targetId LIMIT 1")
                ->fetchColumn();
      //si existe, retorno error 409
      if ($duplicate) {
        $res->getBody()->write(json_encode(['error' => 'Ya existe un usuario con ese email']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')
                  ->withStatus(409);
      }
    }

    $sets = [];

    if ($email !== null) { $sets[] = "email = " . $db->quote($email); }
    if ($password !== null) { $sets[] = "password = " . $db->quote($password); }
    if ($firstName !== null) { $sets[] = "first_name = " . $db->quote($firstName); }
    if ($lastName !== null) { $sets[] = "last_name = " . $db->quote($lastName); }

    //si no hay campos para actualizar, retorno error 400
    if (empty($sets)) {
      $res->getBody()->write(json_encode(['error' => 'El json no puede estar vacio']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

    //implode une los elementos del array con comas
    $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE id = $targetId LIMIT 1";
    $db->exec($sql);

    // devuelvo el modificado
    $row = $db->query("SELECT id, email, first_name, last_name, is_admin FROM users WHERE id = $targetId")
              ->fetch(\PDO::FETCH_ASSOC);

    $res->getBody()->write(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $res->withHeader('Content-Type','application/json; charset=utf-8');
  }
}