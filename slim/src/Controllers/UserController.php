<?php

require_once __DIR__ . '/../Models/UserModel.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController {

    // Handle GET /users
    public static function getUsers(Request $request, Response $response) {
        $users = User::getAll();
        $response->getBody()->write(json_encode($users));
        return $response;
    }
}
