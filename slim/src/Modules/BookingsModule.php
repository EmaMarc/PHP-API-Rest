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
        $sql = $db->prepare ("SELECT * FROM bookings WHERE id = ?");
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


}