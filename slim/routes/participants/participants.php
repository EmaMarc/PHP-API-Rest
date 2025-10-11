<?php
declare(strict_types=1);


require_once __DIR__ . '/../../src/Utils/db.php';
require_once __DIR__ . '/../../src/Modules/ParticipantsModule.php';
require_once __DIR__ . '/../../src/Middlewares/AuthMiddleware.php';



use App\Modules\ParticipantsModule;
use App\Middlewares\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;


// PUT /booking_participant/{id}:
$app->put('/booking_participant/{id}', [ParticipantsModule::class, 'updateParticipant'])->add(new AuthMiddleware());
