<?php


namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';



use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Modules\ParticipantsModule;
use App\Middlewares\AuthMiddleware;
use PDO;


final class ParticipantsModule{

  //verifica si el participante ya esta en la reserva 
  private static function estaEnLaReserva($bookingId, $userId, $db) {
    $stmt = $db->prepare("SELECT * FROM booking_participants WHERE booking_id = ? AND user_id = ?");
    $stmt->execute([$bookingId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row !== false; // Devuelve true si el participante está en la reserva, false en caso contrario
  }

  //verifica si el nuevo participante puede participar en la reserva 
  private static function puedeParticipar(){

  }
   

  // Update participant by ID
  public static function updateParticipant(Request $req, Response $res, array $args): Response {
    //tomo el id de la reserva desde la URL
    $bookingId = (int)($args['id'] ?? 0);// int para que tome solo numeros y si viene vacio o null, le asigno 0

    if ($bookingId <= 0) {//verifico que sea mayor a 0
        $res->getBody()->write(json_encode(['error' => 'ID inválida']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

    if (!$bookingId) {//si esta vacio o null
        $res->getBody()->write(json_encode(['error' => 'Falta el ID de la reserva']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $db = \DB::getConnection();//conexion a la db

    //verifico si la reserva existe
    $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
    $stmt->execute([$bookingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {//si no existe la reserva
            $res->getBody()->write(json_encode(['error' => 'Reserva no encontrada']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(404);
    }

    //aca deberia de verificar el usuario que hace la peticion es admin o es el mismo usuario  que creo la reserva 
    //finjo demencia y no lo hago

    //tomo los id de los nuevos participantes y de los que quiero eliminar desde el body
    $body = $req->getBody();
    $data = json_decode($body, true); // true para que devuelva array asociativo
    

    //COUNT(*) para contar la cantidad de participantes actuales y llamo a ese campo total
    $sql = $db->prepare("SELECT COUNT(*) as total  FROM booking_participants WHERE booking_id = ?");
    $sql->execute([$bookingId]);
    $row = $sql->fetch(PDO::FETCH_ASSOC);
    //recordar que el hacer la consulta devuelve un array asociativo con los campos de la tabla
    //entonces para acceder al campo total hago $row['total'] y lo casteo a int
    $cant_part = (int)$row['total'];// deberia ser 1 (uno vs uno) o 3 (dos vs dos)-y el creador de la reserva no cuenta

    $id_new_1 = (int)$data['new_participants_1'] ?? '' ;//id del nuevo participante 1
    $id_new_2 = (int)$data['new_participants_2'] ?? '' ;//id del nuevo participante 2
    $id_new_3 = (int)$data['new_participants_3'] ?? '' ;//id del nuevo participante 3
    $id_del_1 = (int)$data['del_participants_1'] ?? '' ;//id del participante a eliminar 1
    $id_del_2 = (int)$data['del_participants_2'] ?? '' ;//id del participante a eliminar 2
    $id_del_3 = (int)$data['del_participants_3'] ?? '' ;//id del participante a eliminar 3

    if (($cant_part)==1){//si es uno vs uno, solo puede agregar un participante y eliminar otro
      
      //agrega 3 participantes nuevos y elimina el participante que esta -- pasa a ser dos vs dos
      
      //verifico que los nuevos participantes no esten ya en la reserva y que los participantes a eliminar esten en la reserva
      if (!empty($id_new_1) && !empty($id_new_2) && !empty($id_new_3) && !empty($id_del_1)&& !empty($id_del_2) && !empty($id_del_3)) {
        //verifico que los nuevos participantes no esten ya en la reserva
        if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db) ||
            ParticipantsModule::estaEnLaReserva($bookingId, $id_new_2, $db) ||
            ParticipantsModule::estaEnLaReserva($bookingId, $id_new_3, $db)) {
              $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes ya está en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }
        //verifico que el participante a eliminar este en la reserva
        if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db)) {
              $res->getBody()->write(json_encode(['error' => 'El participante a eliminar no está en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        if (ParticipantsModule::puedeParticipar($id_new_1) ||
            ParticipantsModule::puedeParticipar($id_new_2) ||
            ParticipantsModule::puedeParticipar($id_new_3)) {
              $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no puede participar en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);

        }

        //agrego los nuevos participantes
        $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?), (?, ?), (?, ?)");
        $insert->execute([$bookingId, $id_new_1, $bookingId, $id_new_2, $bookingId, $id_new_3]);

        //elimino el participante
        $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id = ?");
        $delete->execute([$bookingId, $id_del_1]);

        $res->getBody()->write(json_encode(['success' => 'Participantes actualizados correctamente']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200); 

      }

      //agrega dos participantes nuevos y mantiene el participante que esta -- pasa a ser dos vs dos
      //verifico que los nuevos participantes no esten ya en la reserva

      if (!empty($id_new_1) && !empty($id_new_2) && empty($id_new_3) && empty($id_del_1) && empty($id_del_2) && empty($id_del_3)) {
        //verifico que los nuevos participantes no esten ya en la reserva
        if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db) ||
            ParticipantsModule::estaEnLaReserva($bookingId, $id_new_2, $db)) {
              $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes ya está en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }
        //verifico que los participante puedan participar
        if (ParticipantsModule::puedeParticipar($id_new_1) ||
            ParticipantsModule::puedeParticipar($id_new_2)) {
              $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no puede participar en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);

        }

        //agrego los nuevos participantes
        $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?), (?, ?)");
        $insert->execute([$bookingId, $id_new_1, $bookingId, $id_new_2]);

        $res->getBody()->write(json_encode(['success' => 'Participantes actualizados correctamente']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200);

      }

      //agrega un participante nuevo y elimina el participante que esta -- sigue siendo uno vs uno
      //verifico que el nuevo participante no este ya en la reserva y que el participante a eliminar este en la reserva
      if (!empty($id_new_1) && empty($id_new_2) && empty($id_new_3) && !empty($id_del_1) && empty($id_del_2) && empty($id_del_3)) {
        //verifico que el nuevo participante no este ya en la reserva
        if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db)) {
              $res->getBody()->write(json_encode(['error' => 'El nuevo participante ya está en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }
        //verifico que el participante a eliminar este en la reserva
        if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db)) {
              $res->getBody()->write(json_encode(['error' => 'El participante a eliminar no está en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        if (ParticipantsModule::puedeParticipar($id_new_1)) {
              $res->getBody()->write(json_encode(['error' => 'El nuevo participante no puede participar en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);

        }

        //agrego el nuevo participante
        $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?)");
        $insert->execute([$bookingId, $id_new_1]);

        //elimino el participante
        $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id = ?");
        $delete->execute([$bookingId, $id_del_1]);

        $res->getBody()->write(json_encode(['success' => 'Participantes actualizados correctamente']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200);

      }
    }else{
       if (($cant_part)==3){//si es dos vs dos, puede agregar un participante y eliminar hasta dos
        //agrega tres participantes nuevos y elimina los tres participantes que estan -- sigue siendo dos vs dos
        if(!empty($id_new_1) && !empty($id_new_2) && !empty($id_new_3) && !empty($id_del_1) && !empty($id_del_2) && !empty($id_del_3)){
          //verifico que los nuevos participantes no esten ya en la reserva
          if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db) ||
              ParticipantsModule::estaEnLaReserva($bookingId, $id_new_2, $db) ||
              ParticipantsModule::estaEnLaReserva($bookingId, $id_new_3, $db)) {
                $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes ya está en la reserva']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
          }
          //verifico que los participantes a eliminar esten en la reserva
          if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db) ||
              !ParticipantsModule::estaEnLaReserva($bookingId, $id_del_2, $db) ||
              !ParticipantsModule::estaEnLaReserva($bookingId, $id_del_3, $db)) {
                $res->getBody()->write(json_encode(['error' => 'Alguno de los participantes a eliminar no está en la reserva']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
          }

          if (ParticipantsModule::puedeParticipar($id_new_1) ||
              ParticipantsModule::puedeParticipar($id_new_2) ||
              ParticipantsModule::puedeParticipar($id_new_3)) {
                $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no puede participar en la reserva']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);

          }

          //agrego los nuevos participantes
          $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?), (?, ?), (?, ?)");
          $insert->execute([$bookingId, $id_new_1, $bookingId, $id_new_2, $bookingId, $id_new_3]);
          //elimino los participantes
          $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id IN (?, ?, ?)");
          $delete->execute([$bookingId, $id_del_1, $id_del_2, $id_del_3]);  

          $res->getBody()->write(json_encode(['success' => 'Participantes actualizados correctamente']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200);   


        }
        //elimina dos participantes que estan y mantiene un participante -- pasa a ser uno vs uno

        if(!empty($id_del_1) && !empty($id_del_2) && empty($id_del_3) && empty($id_new_1) && empty($id_new_2) && empty($id_new_3)){
          //verifico que los participantes a eliminar esten en la reserva
          if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db) ||
              !ParticipantsModule::estaEnLaReserva($bookingId, $id_del_2, $db)) {
                $res->getBody()->write(json_encode(['error' => 'Alguno de los participantes a eliminar no está en la reserva']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
          }

          //elimino los participantes
          $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id IN (?, ?)");
          $delete->execute([$bookingId, $id_del_1, $id_del_2]);  

          $res->getBody()->write(json_encode(['success' => 'Participantes actualizados correctamente']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200);

        }
        //agrega dos participantes nuevos y elimina dos participantes que estan -- sigue siendo dos vs dos
        if(!empty($id_new_1) && !empty($id_new_2) && empty($id_new_3) && !empty($id_del_1) && !empty($id_del_2) && empty($id_del_3)){
          //verifico que los nuevos participantes no esten ya en la reserva
          if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db) ||
              ParticipantsModule::estaEnLaReserva($bookingId, $id_new_2, $db)) {
                $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes ya está en la reserva']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
          }
          //verifico que los participantes a eliminar esten en la reserva
          if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db) ||
              !ParticipantsModule::estaEnLaReserva($bookingId, $id_del_2, $db)) {
                $res->getBody()->write(json_encode(['error' => 'Alguno de los participantes a eliminar no está en la reserva']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
          }

          if (ParticipantsModule::puedeParticipar($id_new_1) ||
              ParticipantsModule::puedeParticipar($id_new_2)) {
                $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no puede participar en la reserva']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);

          }

          //agrego los nuevos participantes
          $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?), (?, ?)");
          $insert->execute([$bookingId, $id_new_1, $bookingId, $id_new_2]);
          //elimino los participantes
          $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id IN (?, ?)");
          $delete->execute([$bookingId, $id_del_1, $id_del_2]);  

          $res->getBody()->write(json_encode(['success' => 'Participantes actualizados correctamente']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200);   


      }
    }

    //para cualquier otro caso, devuelve error
    $res->getBody()->write(json_encode(['error' => 'No se puede modificar la reserva con los participantes indicados']));
    return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 


  }

  }
}