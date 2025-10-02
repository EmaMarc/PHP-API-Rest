<?php
declare(strict_types=1);

namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;


final class UsersModule {
  //Funciones para users
  
  // GET /users
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

  // GET /user/{id}
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
}