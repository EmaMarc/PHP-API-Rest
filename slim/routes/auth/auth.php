<?php
declare(strict_types=1);

use App\Modules\AuthModule;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


// POST /login
$app->post('/login', [AuthModule::class, 'login']);

// POST /logout
$app->post('/logout', [AuthModule::class, 'logout']);