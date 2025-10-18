<?php

namespace App\Modules;
use App\Utils\Validation;


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once __DIR__ . '/../Utils/Authentication.php';

class AuthModule {
    public static function login(Request $req, Response $res): Response {
      //parseo el body de la request como JSON
      // ahora usamos getParsedBody() que requiere $app->addBodyParsingMiddleware en index.php
      $data = $req->getParsedBody();

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

      if (!Validation::isValidEmail($data['email'])) {
        $res->getBody()->write(json_encode(['error' => 'Email inválido']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
      }

      // ahoca con try catch
      try {
        $db = \DB::getConnection();
        
        //busco el usuario por email 
        //ahora con prepare y execute
        $sql = "SELECT id, password, is_admin
                FROM users
                WHERE email = ?
                LIMIT 1";

        // preparo y ejecuto la consulta
        $st = $db->prepare($sql);
        $st->execute([$email]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        //si no existe la fila, devuelvo error 401
        if (!$row) {
          $res->getBody()->write(json_encode(['error' => 'Email no registrado en el sistema']));
          return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(401);
        }

        //Verifico la password
        if (!\Authentication::verifyPassword($password, $row['password'])) {
          $res->getBody()->write(json_encode(['error' => 'Contraseña incorrecta']));
          return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(401);
        }

        //genero un token nuevo
        $token = \Authentication::makeToken($db, (int)$row['id']);
        //si no se pudo generar el token, devuelvo error 500
        if ($token === null) {
          $res->getBody()->write(json_encode(['error' => 'No se pudo generar el token']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
        }

        //devuelvo el token
        $res->getBody()->write(json_encode(['token' => $token]));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200);
      } catch (\Throwable $e) {
        //ahora un catch general para errores de BD
        error_log($e);
        $res->getBody()->write(json_encode(['error' => 'Error interno con la base de datos']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
      }
    }



public static function logout(Request $req, Response $res): Response {
      //Leo json y extraigo el token ahora desde el header Authorization
      $authHeader = $req->getHeaderLine('Authorization'); 
      $token = '';
      if (stripos($authHeader, 'Bearer ') === 0) {
        $token = trim(substr($authHeader, 7));
      }

      //si falta el token, devuelvo error 400
      if ($token === '') {
        $res->getBody()->write(json_encode(['error' => 'Falta token']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
      }

      try { 
        // ahora manejo de errores con try/catch
        $db = \DB::getConnection();


        
        //aca busco el token y lo elimino de la base de datos
        $sql = "UPDATE users SET token = NULL, expired = NULL WHERE token = ?";
        // ahora con prepare y execute
        $st = $db->prepare($sql);
        $ok = $st->execute([$token]);

        //si no se pudo ejecutar la consulta, devuelvo error 500
        if ($ok === false) {
          $res->getBody()->write(json_encode(['error' => 'Error al ejecutar la consulta']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
        }

        //si se actualizaron 0 filas, el token era inválido o ya deslogueado
        if ($st->rowCount() === 0) {
          $res->getBody()->write(json_encode(['error' => 'Token inválido']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(401);
        }

        $res->getBody()->write(json_encode(['status' => 'Logout exitoso']));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);

      } catch (\Throwable $e) { 
        //ahora un catch general para errores de BD
        error_log($e);
        $res->getBody()->write(json_encode(['error' => 'Error interno']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
      }
    }
}