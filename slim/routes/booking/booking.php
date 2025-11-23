<?php
declare(strict_types=1);


require_once __DIR__ . '/../../src/Utils/db.php';


use App\Modules\BookingsModule;
use App\Middlewares\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


//POST crea una reserva
$app->post('/booking', [BookingsModule::class, 'crearReserva'])->add(new AuthMiddleware());

//DELETE elimina una reserva
$app->delete('/booking/{id}', [BookingsModule::class, 'eliminar'])->add(new AuthMiddleware()); 


//GET muestras las reservas de un dia    /bookings?date=2025-10-06
//no hace falta ponerle lo que esta despues del ? slim no lo reconoce 
$app->get('/booking', [BookingsModule::class, 'reservas']);


//definidos para el frontend

////get /booking/participants/{id} - muestra los participantes de una reserva
$app->get('/booking/participants/{id}', [BookingsModule::class, 'obtenerNombresParticipantes']);


////get /booking/{id} - muestra la informacion de una reserva
$app->get('/booking/{id}', [BookingsModule::class, 'infoReserva']);