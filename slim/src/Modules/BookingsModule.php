<?php

 // DB imported
 

 namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';



use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

final class BookingsModule{

    public static function eliminar(Request $req, Response $res, array $args): Response {
        $id = (int)($args['id']);//id desde la ruta y verifica que sea un nro

        if (!$id) {
            
            $res->getBody()->write(json_encode(['error' => 'Falta el ID de la reserva']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = \DB::getConnection();

        //verifico si la reserva existe 
        $sql = $db->prepare ("SELECT * FROM bookings AS b INNER JOIN courts AS c ON b.court_id = c.id 
                                WHERE b.id = ? ORDER BY c.name ASC, b.booking_datetime ASC");
                                    //funcionna pero ver xq no muentra los nobres de la cancha
        $sql->execute([$id]); //retorna F o V
        $row = $sql->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($row)){

            $elim = $db->prepare ("DELETE FROM bookings WHERE id = ?");
            $elim->execute([$id]); //retorna F o V

            //$res->getBody()->write(json_encode(['ok' => true, 'deleted_id' => $id]));
            //return $res->withHeader('Content-Type','application/json; charset=utf-8');

            $res->getBody()->write(json_encode(['success' => 'reserva eliminada']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(200);

        }else {
            $res->getBody()->write(json_encode(['error' => 'No se encontró la reserva a eliminar']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        


    }


    public static function reservas(Request $req, Response $res): Response {
        //query params lo que esta despues del ?    /booking?date={date}
        $queryParams = $req->getQueryParams(); // devuelve un array asociativo con los query params
        $date = $queryParams['date'] ?? null;

        if (!$date) {
            
            $res->getBody()->write(json_encode(['error' => 'Falta el dia de la reserva']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = \DB::getConnection();

        //verifico si la reserva existe 
        $sql = $db->prepare ("SELECT * FROM bookings WHERE DATE(booking_datetime) = ?");//date toma solo el dia
        $sql->execute([$date]); //retorna F o V
        $row = $sql->fetchAll(PDO::FETCH_ASSOC); //devuelve todos los resultados

        if (!empty($row)){
            //devuelve las reservas encontradas de ese dia 
            $res->getBody()->write(json_encode($row));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(200);

        }else{
            $res->getBody()->write(json_encode(['error' => 'No se encontró reservas']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        


    }

}