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

    $sql = "SELECT id, email, first_name, last_name, is_admin
            FROM users
            ORDER BY id DESC";

    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $res->getBody()->write(json_encode($rows, JSON_UNESCAPED_UNICODE));
    return $res->withHeader('Content-Type','application/json'); // 200 por defecto
  }
}
