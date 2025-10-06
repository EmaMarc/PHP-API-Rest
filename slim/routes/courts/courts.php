<?php
declare(strict_types=1);


require_once __DIR__ . '/../../src/Utils/db.php';


use App\Modules\CourtsModule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;



$app->get('/court', [CourtsModule::class, 'getAll']); 


// POST crea una cancha
$app->post('/court', [CourtsModule::class, 'createCourts']); 

//PUT edita una cancha
$app->put('/court/{id}', [CourtsModule::class, 'editar']); 

//DELETE elimina una cancha 
$app->delete('/court/{id}', [CourtsModule::class, 'eliminar']); 


//GET /{id} obtiene la informacion de cancha
$app->get('/court/{id}', [CourtsModule::class, 'getCourtsById']); 