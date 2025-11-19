<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add( function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
        ->withHeader('Content-Type', 'application/json')
    ;
});

//
$app->addBodyParsingMiddleware();

// ACÁ VAN LOS ENDPOINTS 

// Auth
require __DIR__ . '/routes/auth/auth.php';

// Users
require __DIR__ . '/routes/users/users.php';

// Courts
require __DIR__ . '/routes/courts/courts.php';
    


// Booking
require __DIR__ . '/routes/booking/booking.php';

// Participants
require __DIR__ . '/routes/participants/participants.php';

// server works?
$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("API REST para Seminario de PHP works! - Emanuel Marcello y Lizeth Ordoñez");
    return $response;
});

//php -S localhost:8000
$app->run();
