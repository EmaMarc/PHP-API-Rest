<?php
declare(strict_types=1);


require_once __DIR__ . '/../../src/Utils/db.php';


use App\Modules\BookingsModule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


//DELETE elimina una reserva
$app->delete('/booking/{id}', [BookingsModule::class, 'eliminar']); 


//GET muestras las reservas de un dia    /bookings?date=2025-10-06
//no hace falta ponerle lo que esta despues del ? slim no lo reconoce 
$app->get('/booking', [BookingsModule::class, 'reservas']);