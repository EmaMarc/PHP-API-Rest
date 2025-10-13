<?php


namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';
require_once __DIR__ . '/../Utils/Authentication.php';



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
    return $row !== false; //true si esta en la reserva false si no
  }

  //verifica si el nuevo participante puede participar en la reserva 
  private static function puedeParticipar($id, $dia_hora_reserva, $cant_bloques, $db) {
    //suma minutos y con strtotime la interpreta en lenguaje natural (usa un parser interno de PHP). y el resultado lo pasa a tipo date
    //$fin_reserva = date('Y-m-d H:i:s', strtotime($inicioReserva . ' + ' . (30 * $bloques) . ' minutes'));


    //calculo la hora de fin de la reserva sumando los bloques (30 minutos cada uno)
    //le suma segundos bloques * 30 minutos * 60 segundos
    $fin_reserva = date('Y-m-d H:i:s', strtotime($dia_hora_reserva) + ($cant_bloques * 30 * 60)); 

    // Verifico si el usuario ya tiene una reserva que se cruce con la nueva
    $stmt = $db->prepare("SELECT u.* 
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

    //tomo los datos de la reserva
    $dia_hora_reserva = $row['booking_datetime'];
    $duracion_reserva = (int)$row['duration_blocks']; //en bloques de 30 minutos
    $id_creador_reserva = (int)$row['created_by'];//id del usuario que creo la reserva

    

    //aca deberia de verificar el usuario que hace la peticion es admin o es el mismo usuario  que creo la reserva 
    $auth = $req->getAttribute('auth_user');
    //pregunto si esta autorizado y le paso auth y id del usuario a modificar
    if (!\Authentication::tienePermiso($auth, $id_creador_reserva)) {
        $res->getBody()->write(json_encode(['error' => 'Usuario No autorizado']));
        return  $res->withHeader('Content-Type','application/json; charset=utf-8')
            ->withStatus(401);
    }/*else{ //para probar
        $res->getBody()->write(json_encode(['ok' => 'Usuario autorizado']));
        return  $res->withHeader('Content-Type','application/json; charset=utf-8')
            ->withStatus(200);
    }*/

    //si el token es valido, refresco su expiración
    \Authentication::refreshToken($db, $auth['id'], 300);



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

    
    //no importa si es uno vs uno o dos vs dos, ambos podrian agrear un participante y eliminar otro
    if ((!empty($id_new_1) && empty($id_new_2) && empty($id_new_3) )&& (!empty($id_del_1) && empty($id_del_2) && empty($id_del_3))) {
      

      //verifico que el nuevo participante este en la base de datos
      $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE id = ?");
      $stmt->execute([$id_new_1]);  
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ((int)$row['total'] != 1) { //deberia ser 1
            $res->getBody()->write(json_encode(['error' => 'El nuevo participante no existe']));
            return $res->withHeader('Content-Type','application/json; charset=utf -8')->withStatus(400); 
      }
      
      //verifico que el nuevo participante no este ya en la reserva
      if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db)) {
            $res->getBody()->write(json_encode(['error' => 'El nuevo participante ya está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
      }

      //verifico que el participante a eliminar no sea el creador de la reserva
      if ($id_del_1 === $id_creador_reserva) {  
            $res->getBody()->write(json_encode(['error' => 'No se puede eliminar al creador de la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
      } 


      //verifico que el participante a eliminar este en la reserva
      if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db)) {
            $res->getBody()->write(json_encode(['error' => 'El participante a eliminar no está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
      } 

      //verifico que el nuevo participante pueda participar
      if (!ParticipantsModule::puedeParticipar($id_new_1, $dia_hora_reserva, $duracion_reserva, $db)) {
            $res->getBody()->write(json_encode(['error' => 'El nuevo participante no puede participar en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);

      }

      //si paso todas las verificaciones, agrego el nuevo participante

      /* preguntar para este caso cual es mejor, si hacer un update o un insert y un delete

      $insert = $db->prepare("UPDATE booking_participants
                              SET user_id = ?
                              WHERE booking_id = ? AND user_id = ?");
      $insert->execute([$id_new_1, $bookingId, $id_del_1]);
      */


      $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?)");
      $insert->execute([$bookingId, $id_new_1]);  

      //elimino el participante
      $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id = ?");
      $delete->execute([$bookingId, $id_del_1]);  

      $res->getBody()->write(json_encode(['success' => 'Participantes actualizado correctamente']));
      return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200); 


    }


    // si la reserva es  uno vs uno,
    if(($cant_part) == 2){
      //dos casos posibles para pasar de uno vs uno a dos vs dos

      //1- agregar dos participante (sin eliminar a nadie) -agrega a dos participantes--------------------------------------------------------------------------

      if((!empty($id_new_1) && !empty($id_new_2) && empty($id_new_3) )&& (empty($id_del_1) && empty($id_del_2) && empty($id_del_3))) {
        
        //verifico que los nuevos participantes sean no sean iguales entre si
        if ($id_new_1 === $id_new_2) {
            $res->getBody()->write(json_encode(['error' => 'Los nuevos participantes no pueden ser iguales entre sí']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los nuevos participantes estes en la base de datos
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE id IN (?, ?)");
        $stmt->execute([$id_new_1, $id_new_2]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int)$row['total'] != 2) { //deberian ser 2
            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no existe']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los nuevos participantes no esten ya en la reserva
        if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db) || ParticipantsModule::estaEnLaReserva($bookingId, $id_new_2, $db)) {
            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes ya está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }
        //verifico que los nuevos participantes puedan participar
        if (!ParticipantsModule::puedeParticipar($id_new_1, $dia_hora_reserva, $duracion_reserva, $db) || 
            !ParticipantsModule::puedeParticipar($id_new_2, $dia_hora_reserva, $duracion_reserva, $db)) {
            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no puede participar porque ya tinen una reserva ']));
            return $res->withHeader ('Content-Type','application/json; charset=utf-8')->withStatus(400);  
        }

        //si paso todas las verificaciones, agrego los nuevos participantes
        $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?)");
        $insert->execute([$bookingId, $id_new_1]);  
        $insert->execute([$bookingId, $id_new_2]);  

        //informo que fue exitoso
        $res->getBody()->write(json_encode(['success' => 'Participantes agregados correctamente']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200); 
  
      }

      //2-agregar 3 participantes y eliminar 1 (pasa de uno vs uno a dos vs dos)--------------------------------------------------------------------------------
      if((!empty($id_new_1) && !empty($id_new_2) && !empty($id_new_3) )&& (!empty($id_del_1) && empty($id_del_2) && empty($id_del_3))) {
        
        //verifico que los nuevos participantes sean no sean iguales entre si
        if ($id_new_1 === $id_new_2 || $id_new_1 === $id_new_3 || $id_new_2 === $id_new_3) {
            $res->getBody()->write(json_encode(['error' => 'Los nuevos participantes no pueden ser iguales entre sí']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los nuevos participantes estes en la base de datos
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE id IN (?, ?, ?)");
        $stmt->execute([$id_new_1, $id_new_2, $id_new_3]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int)$row['total'] != 3) { //deberian ser 3
            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no existe']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los nuevos participantes no esten ya en la reserva
        if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db) || 
            ParticipantsModule::estaEnLaReserva($bookingId, $id_new_2, $db) || 
            ParticipantsModule::estaEnLaReserva($bookingId, $id_new_3, $db)) {

            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes ya está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }
        //verifico que el participante a eliminar no sea el creador de la reserva
        if ($id_del_1 === $id_creador_reserva) {  
            $res->getBody()->write(json_encode(['error' => 'No se puede eliminar al creador de la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que el participante a eliminar este en la reserva
        if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db)) {
            $res->getBody()->write(json_encode(['error' => 'El participante a eliminar no está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        } 

        //verifico que los nuevos participantes puedan participar
        if (!ParticipantsModule::puedeParticipar($id_new_1, $dia_hora_reserva, $duracion_reserva, $db) ||
           !ParticipantsModule::puedeParticipar($id_new_2, $dia_hora_reserva, $duracion_reserva  , $db) ||
            !ParticipantsModule::puedeParticipar($id_new_3, $dia_hora_reserva, $duracion_reserva, $db)) {
            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no puede participar porque ya tinen una reserva ']));
            return $res->withHeader ('Content-Type','application/json; charset=utf-8')->withStatus(400);  
        } 

        //si paso todas las verificaciones, agrego los nuevos participantes
        $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?)");
        $insert->execute([$bookingId, $id_new_1]);  
        $insert->execute([$bookingId, $id_new_2]);  
        $insert->execute([$bookingId, $id_new_3]);

        //elimino el participante
        $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id = ?");
        $delete->execute([$bookingId, $id_del_1]);  

        //informo que fue exitoso
        $res->getBody()->write(json_encode(['success' => 'Participantes actualizado correctamente']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200); 

      }
    }

    // si la reserva es  dos vs dos,
    if(($cant_part == 4)){
      //unico caso de pasar a uno vs uno
      //eliminar dos participantes (sin agregar a nadie) -pasa de dos vs dos a uno vs uno--------------------------------------------------------------------------
      if((empty($id_new_1) && empty($id_new_2) && empty($id_new_3) )&& (!empty($id_del_1) && !empty($id_del_2) && empty($id_del_3))) {
        
        //verifico que los participantes a eliminar no sean iguales entre si
        if ($id_del_1 === $id_del_2) {
            $res->getBody()->write(json_encode(['error' => 'Los participantes a eliminar no pueden ser iguales entre sí']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los participantes a eliminar no sean el creador de la reserva
        if ($id_del_1 === $id_creador_reserva || $id_del_2 === $id_creador_reserva) {  
            $res->getBody()->write(json_encode(['error' => 'No se puede eliminar al creador de la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        } 

        //verifico que los participantes a eliminar esten en la reserva
        if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db) || 
            !ParticipantsModule::estaEnLaReserva($bookingId, $id_del_2, $db)) {
            $res->getBody()->write(json_encode(['error' => 'Alguno de los participantes a eliminar no está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        } 

        //si paso todas las verificaciones, elimino los participantes
        $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id = ?");
        $delete->execute([$bookingId, $id_del_1]);  
        $delete->execute([$bookingId, $id_del_2]);  

        //informo que fue exitoso
        $res->getBody()->write(json_encode(['success' => 'Participantes eliminados correctamente']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200); 
  
      }



      //dos casos para mantener dos vs dos
     //elimina dos y agrega dos -sigue dos vs dos-------------------------------------------------------------------------------------------------------------------
      if ((!empty($id_new_1) && !empty($id_new_2) && empty($id_new_3) )&& (!empty($id_del_1) && !empty($id_del_2) && empty($id_del_3))){
        
        //verifico que los nuevos participantes sean no sean iguales entre si
        if ($id_new_1 === $id_new_2) {
            $res->getBody()->write(json_encode(['error' => 'Los nuevos participantes no pueden ser iguales entre sí']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los participantes a eliminar no sean iguales entre si
        if ($id_del_1 === $id_del_2) {
            $res->getBody()->write(json_encode(['error' => 'Los participantes a eliminar no pueden ser iguales entre sí']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los nuevos participantes estes en la base de datos
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE id IN (?, ?)");
        $stmt->execute([$id_new_1, $id_new_2]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int)$row['total'] != 2) { //deberian ser 2
            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no existe']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los nuevos participantes no esten ya en la reserva
        if (ParticipantsModule::estaEnLaReserva($bookingId, $id_new_1, $db) || ParticipantsModule::estaEnLaReserva($bookingId, $id_new_2, $db)) {
            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes ya está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }
        //verifico que los nuevos participantes puedan participar
        if (!ParticipantsModule::puedeParticipar($id_new_1, $dia_hora_reserva, $duracion_reserva, $db) || 
            !ParticipantsModule::puedeParticipar($id_new_2, $dia_hora_reserva, $duracion_reserva, $db)) {
            $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no puede participar porque ya tinen una reserva ']));
            return $res->withHeader ('Content-Type','application/json; charset=utf-8')->withStatus(400);  
        } 

        //verifico que los participantes a eliminar no sean el creador de la reserva
        if ($id_del_1 === $id_creador_reserva || $id_del_2 === $id_creador_reserva) {  
            $res->getBody()->write(json_encode(['error' => 'No se puede eliminar al creador de la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        } 

        //verifico que los participantes a eliminar esten en la reserva
        if (!ParticipantsModule::estaEnLaReserva($bookingId, $id_del_1, $db) || 
            !ParticipantsModule::estaEnLaReserva($bookingId, $id_del_2, $db)) {
            $res->getBody()->write(json_encode(['error' => 'Alguno de los participantes a eliminar no está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }   

        //si paso todas las verificaciones, agrego los nuevos participantes
        $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?)");
        $insert->execute([$bookingId, $id_new_1 ]);  
        $insert->execute([$bookingId, $id_new_2]);  

        //elimino los participantes 
        $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id = ?");
        $delete->execute([$bookingId, $id_del_1]);  
        $delete->execute([$bookingId, $id_del_2]);    

        $res->getBody()->write(json_encode(['success' => 'Participantes actualizado correctamente']));  
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200); 

      }

      //elimina 3 y agrega 3  -sigue dos vs dos --------------------------------------------------------------------------------------------------------------------
      if(!empty($id_new_1) && !empty($id_new_2) && !empty($id_new_3) && !empty($id_del_1) && !empty($id_del_2) && !empty($id_del_3)){

        //verifico que los nuevos participantes sean no sean iguales entre si
        if ($id_new_1 === $id_new_2 || $id_new_1 === $id_new_3 || $id_new_2 === $id_new_3) {
            $res->getBody()->write(json_encode(['error' => 'Los nuevos participantes no pueden ser iguales entre sí']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los participantes a eliminar no sean iguales estre si
        if ($id_del_1 === $id_del_2 || $id_del_1 === $id_del_3 || $id_del_2 === $id_del_3) {
            $res->getBody()->write(json_encode(['error' => 'Los participantes a eliminar no pueden ser iguales entre sí']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

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

        //verifico que los participantes a eliminar no sean el creador de la reserva
        if ($id_del_1 === $id_creador_reserva || $id_del_2 === $id_creador_reserva || $id_del_3 === $id_creador_reserva) {  
              $res->getBody()->write(json_encode(['error' => 'No se puede eliminar al creador de la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }

        //verifico que los nuevos participantes puedan participar
        if (!ParticipantsModule::puedeParticipar($id_new_1, $dia_hora_reserva, $duracion_reserva, $db) ||
            !ParticipantsModule::puedeParticipar($id_new_2, $dia_hora_reserva, $duracion_reserva, $db) ||
            !ParticipantsModule::puedeParticipar($id_new_3, $dia_hora_reserva, $duracion_reserva, $db)) {
              $res->getBody()->write(json_encode(['error' => 'Alguno de los nuevos participantes no puede participar en la reserva']));
              return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
        }

        //si paso todas las verificaciones,
        //agrego los nuevos participantes
        $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?), (?, ?), (?, ?)");
        $insert->execute([$bookingId, $id_new_1, $bookingId, $id_new_2, $bookingId, $id_new_3]);
        //elimino los participantes
        $delete = $db->prepare("DELETE FROM booking_participants WHERE booking_id = ? AND user_id IN (?, ?, ?)");
        $delete->execute([$bookingId, $id_del_1, $id_del_2, $id_del_3]);  

        $res->getBody()->write(json_encode(['success' => 'Participantes actualizados correctamente']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200);   


        }
    
    }



     //para cualquier otro caso, devuelve error
    $res->getBody()->write(json_encode(['error' => 'No se puede modificar la reserva con los participantes indicados']));
    return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 


  }

}
