<?php

namespace App\Modules;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../Utils/Authentication.php';

class AuthModule {
    public static function login(Request $req, Response $res): Response {
      //parseo el body de la request como JSON
      $data = json_decode((string)$req->getBody(), true);
      //si no es un array, devuelvo error 400
      if (!is_array($data)) {        
        $res->getBody()->write(json_encode(['error' => 'JSON inválido']));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
      }
      
      //obtengo email y password del body
      $email = (string)($data['email'] ?? '');
      $password = (string)($data['password'] ?? '');

      //si falta email o password, devuelvo error 400
      if ($email === '' || $password === '') {
        $res->getBody()->write(json_encode(['error' => 'Falta email y/o password']));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(400);
      }

      $db = \DB::getConnection();
      
      //quote es
      $emailQ = $db->quote($email);
      $sql = "SELECT id, password, is_admin
        FROM users
        WHERE email = $emailQ
        LIMIT 1";

      //row = la fila que devuelve la consulta
      $row = $db->query($sql)->fetch(\PDO::FETCH_ASSOC);

      //si no existe la fila, devuelvo error 401
      if (!$row) {
        $res->getBody()->write(json_encode(['error' => 'Credenciales inválidas']));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(401);
      }

      //Verifico la password
      if (!\Authentication::verifyPassword($password, $row['password'])) {
        $res->getBody()->write(json_encode(['error' => 'Credenciales incorrectas']));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(401);
      }

      //genero un token nuevo
      $token = \Authentication::makeToken($db, (int)$row['id']);
      //si no se pudo generar el token, devuelvo error 500
      if ($token === null) {
        $res->getBody()->write(json_encode(['error' => 'No se pudo actualizar el token']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
      }

      //devuelvo el token
      $res->getBody()->write(json_encode(['token' => $token]));
      return $res->withHeader('Content-Type','application/json; charset=utf-8');
    }


    public static function logout(Request $req, Response $res): Response {
      //Leo json y extraigo el token
      $data  = json_decode((string)$req->getBody(), true);
      $token = (string)($data['token'] ?? '');

      //si falta el token, devuelvo error 400
      if ($token === '') {
        $res->getBody()->write(json_encode(['error' => 'Falta token']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
      }

      $db = \DB::getConnection();
      $tokenQ  = $db->quote($token);
      $sql     = "UPDATE users SET token = NULL, 
                  expired = NULL WHERE token = $tokenQ";
      $ok = $db->exec($sql);

      //si no se pudo ejecutar la consulta, devuelvo error 500
      if ($ok === false) {
        $res->getBody()->write(json_encode(['error' => 'Error al ejecutar la consulta']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
      }

      //si se actualizaron 0 filas, el token era inválido o ya deslogueado
      if ($ok === 0) {
        $res->getBody()->write(json_encode(['error' => 'Token inválido']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(401);
    }

      $res->getBody()->write(json_encode(['status' => 'Logout exitoso']));
      return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}