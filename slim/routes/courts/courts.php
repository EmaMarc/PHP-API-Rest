<?php
declare(strict_types=1);


require_once __DIR__ . '/../../src/Utils/db.php';


use App\Modules\CourtsModule;
use App\Middlewares\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;





// POST crea una cancha
$app->post('/court', [CourtsModule::class, 'createCourts'])->add(new AuthMiddleware()); 

//PUT edita una cancha
$app->put('/court/{id}', [CourtsModule::class, 'editar'])->add(new AuthMiddleware()); 

//DELETE elimina una cancha 
$app->delete('/court/{id}', [CourtsModule::class, 'eliminar'])->add(new AuthMiddleware()); 


//GET /{id} obtiene la informacion de cancha
//$app->get('/court/{id}', [CourtsModule::class, 'getCourtsById']); 


//se agrego solo para el frontend
//GET  obtiene la informacion de TODAS las cancha con query params opcionales
$app->get('/court', [CourtsModule::class, 'getCourts']); 


