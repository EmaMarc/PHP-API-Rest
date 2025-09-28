<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Utils/DB.php';


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

// GET /users/getAllUsers  → lista todos los usuarios (sin password/token)
$app->get('/users/getAllUsers', function (Request $req, Response $res) {
    $db = DB::getConnection();

    $sql = "SELECT id, email, first_name, last_name, is_admin
            FROM users
            ORDER BY id DESC";

    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $res->getBody()->write(json_encode($rows, JSON_UNESCAPED_UNICODE));
    return $res->withHeader('Content-Type', 'application/json')->withStatus(200);
});