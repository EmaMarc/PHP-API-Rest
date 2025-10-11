<?php

 // DB imported
 

 namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';



use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use DateTime; //para verificar que las fechas tengan un formato correcto


final class BookingsModule{
  
      //verifica si el nuevo participante puede participar en la reserva 
    private static function puedeParticipar($id, $dia_hora_reserva, $cant_bloques, $db) {
      //suma minutos y con strtotime la interpreta en lenguaje natural (usa un parser interno de PHP). y el resultado lo pasa a tipo date
      //$fin_reserva = date('Y-m-d H:i:s', strtotime($inicioReserva . ' + ' . (30 * $bloques) . ' minutes'));


      //calculo la hora de fin de la reserva sumando los bloques (30 minutos cada uno)
      //le suma segundos bloques * 30 minutos * 60 segundos
      $fin_reserva = date('Y-m-d H:i:s', strtotime($dia_hora_reserva) + ($cant_bloques * 30 * 60)); 

      // Verifico si el usuario ya tiene una reserva que se cruce con la nueva
      $stmt = $db->prepare("
          SELECT u.* 
          FROM users u
          INNER JOIN booking_participants part ON u.id = part.user_id
          INNER JOIN bookings b ON part.booking_id = b.id
          WHERE u.id = ?
          AND b.booking_datetime < ? 
          AND DATE_ADD(b.booking_datetime, INTERVAL b.duration_blocks * 30 MINUTE) > ?");  

      $stmt->execute([$id, $fin_reserva, $dia_hora_reserva]);

      // Si hay resultados, significa que ya tiene otra reserva en ese horario true (no puede participar)
      // Si no hay resultados, puede participar false
      return  !($stmt->fetch(PDO::FETCH_ASSOC)); //lo niega si no hay resultados true (puede participar) 
                                                // si hay resultados false (no puede participar)
    }

    // POST /booking -------------------------------------------------------------------------------
    public static function crearReserva(Request $req, Response $res): Response {
        
        $body = $req->getBody();
        $data = json_decode($body, true); // true para que devuelva array asociativo

        //desde el body se esperan estos campos los id de los compañeros elegidos, dia y hora (en un solo campo)
        //cantidad de bloques (30 minutos cada uno)(que no supere 6 ose 3hs) y que no se ecesa de las 22hs y el id de la cancha
        
        $id_new_1 = (int)$data['new_participants_1'] ?? '' ;//id del nuevo participante 1
        $id_new_2 = (int)$data['new_participants_2'] ?? '' ;//id del nuevo participante 2
        $id_new_3 = (int)$data['new_participants_3'] ?? '' ;//id del nuevo participante 3
        $dia_hora_reserva = $data['booking_datetime'] ?? '' ;//dia y hora de la reserva
        $cant_bloques = (int)$data['duration_blocks'] ?? 0 ;//cantidad de bloques (30 minutos cada uno)
        $court_id = (int)$data['court_id'] ?? 0 ;//id de la cancha

        $fechaObj = new DateTime($dia_hora_reserva);//crea un objeto DateTime a partir de la cadena de fecha y hora





    }


    // DELETE /booking/{id} -------------------------------------------------------------------------------
    public static function eliminar(Request $req, Response $res, array $args): Response {
        
        $id = (int)($args['id']);//id desde la ruta y verifica que sea un nro

        if (!$id) {//
            
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

// GET /booking?date={date} -------------------------------------------------------------------------------
    public static function reservas(Request $req, Response $res): Response {
        //query params lo que esta despues del ?    /booking?date={date}
        $queryParams = $req->getQueryParams(); // devuelve un array asociativo con los query params


        $date = $queryParams['date'] ?? null;

        if (!$date) {
            
            $res->getBody()->write(json_encode(['error' => 'Falta el dia de la reserva']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        //  Validar que la fecha tenga el formato correcto y sea válida

        //Intenta parsear el string $date según el formato 'Y-m-d', si no puede devuelve false
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        //$dateObj pordria devolver un obj por eso se chequea errores
        $errors = DateTime::getLastErrors();//Recupera un array con información sobre la última operación de parseo

        //si la cantidad de errores o advertencias es mayor a 0 o si no se pudo crear el objeto
        if (!$dateObj || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
            $res->getBody()->write(json_encode(['error' => 'Fecha inválida o mal formada']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }


        $db = \DB::getConnection();

        //verifico si la reserva existe 
        $sql = $db->prepare ("SELECT b.id, b.booking_datetime, b.duration_blocks, c.name AS court_name, c.description AS court_description 
                            FROM bookings b  INNER JOIN courts c ON b.court_id = c.id
                            WHERE DATE(booking_datetime) = ?
                            ORDER BY c.name, b.booking_datetime ");//date toma solo el dia
        $sql->execute([$date]); //retorna F o V
        $row = $sql->fetchAll(PDO::FETCH_ASSOC); //devuelve todos los resultados

        if (!empty($row)){
            //devuelve las reservas encontradas de ese dia 
            $res->getBody()->write(json_encode($row));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(200);

        }else{
            $res->getBody()->write(json_encode(['error' => 'No se encontró reservas para ese dia']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        

    }

}