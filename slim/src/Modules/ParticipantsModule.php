<?php


namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';



use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Modules\ParticipantsModule;
use App\Middlewares\AuthMiddleware;
use PDO;


final class ParticipantsModule{
  // Update participant by ID
  public static function updateParticipant(Request $req, Response $res, array $args): Response {
    $bookingId = (int)($args['id'] ?? 0);
    if ($bookingId <= 0) {
        $res->getBody()->write(json_encode(['error' => 'ID inválida']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

    $auth = $req->getAttribute('auth_user'); // id y is_admin desde el token (validado por el middleware)

    $db = \DB::getConnection();

    $row = $db->query(
            "SELECT id, user_id, court_id
              FROM bookings
              WHERE id = $bookingId
              LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC);

    if (!$row) {
            $res->getBody()->write(json_encode(['error' => 'Reserva no encontrada']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(404);
    }

    //creador de la reserva o admin
    $ownerId = (int)$row['user_id'];
    if (!\Authentication::isAuthorized($auth, $ownerId)) {
        $res->getBody()->write(json_encode(['error' => 'No autorizado']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(401);
    }


    $res->getBody()->write(json_encode(['status' => 'owner ok']));
    return $res->withHeader('Content-Type','application/json; charset=utf-8');
  }

}