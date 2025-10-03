<?php
declare(strict_types=1);


require_once __DIR__ . '/../../src/Utils/db.php';

use App\Modules\UsersModule;
use App\Middlewares\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


// GET /users - Get users
$app->get('/allUsers', [UsersModule::class, 'getAllUsers']);

// GET /users?search={text}: - searchUsers
$app->get('/users', [UsersModule::class, 'searchUsers']);

// GET /user/{id} - getUserById
$app->get('/user/{id}', [UsersModule::class, 'getUserById']);

// POST /user - createUser
$app->post('/user', [UsersModule::class, 'createUser']);

// PATCH /user/{id} - updateUser
$app->patch('/user/{id}', [UsersModule::class, 'updateUser'])->add(new AuthMiddleware());

// DELETE /user/{id} - deleteUser
$app->delete('/user/{id}', [UsersModule::class, 'deleteUser'])->add(new AuthMiddleware());
