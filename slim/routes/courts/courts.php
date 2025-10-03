<?php
declare(strict_types=1);


require_once __DIR__ . '/../../src/Utils/db.php';
use App\Modules\CourtsModule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


// GET /users - Get all users
$app->get('/users', [UsersModule::class, 'getAllUsers']);


// GET /user/{id} - getUserById
$app->get('/user/{id}', [UsersModule::class, 'getUserById']);