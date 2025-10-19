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

    //verifica si el usuario existe en la base de datos
    private static function validarUsuario($id, $db) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? true : false; //retorna true si el usuario existe, false si no
    }


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


    if (!$bookingId) {//si esta vacio o null
        $res->getBody()->write(json_encode(['error' => 'Falta el ID de la reserva']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    if ($bookingId <= 0) {//verifico que sea mayor a 0
        $res->getBody()->write(json_encode(['error' => 'ID inválida']));
        return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
    }

   try {
    
        $db = \DB::getConnection();//conexion a la db


        //verifico si la reserva existe
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {//si no existe la reserva
                $res->getBody()->write(json_encode(['error' => 'Reserva no encontrada']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(404);
        }


        //tomo los datos de la reserva a modificar 
        $dia_hora_reserva = $row['booking_datetime'];
        $duracion_reserva = (int)$row['duration_blocks']; //en bloques de 30 minutos - cantidad de bloques 
        $id_creador_reserva = (int)$row['created_by'];//id del usuario que creo la reserva



        //aca deberia de verificar el usuario que hace la peticion es admin o es el mismo usuario  que creo la reserva 
        $auth = $req->getAttribute('auth_user');
        //pregunto si esta autorizado y le paso auth y id del usuario a modificar
        if (!\Authentication::tienePermiso($auth, $id_creador_reserva)) {
            $res->getBody()->write(json_encode(['error' => 'Usuario No autorizado']));
            return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                ->withStatus(401);
        }

        //si el token es valido, refresco su expiración
        \Authentication::refreshToken($db, $auth['id'], 300);

        // ahora con body parsing middleware
        $data = $req->getParsedBody();


        // Verificar que los datos existan antes de usarlos
         if (!$data || !is_array($data)) {//verifica que se hayan recibido datos
            $res->getBody()->write(json_encode(['error' => 'No se recibieron datos válidos']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        //tomo los participantes a agreagar 
        $participants = $data['participants'] ?? [];//vector con los id de los participantes

        // Validar que participantes sea un arreglo ejem que no sea 3 , "hola",  "3,4,5"
        if (!is_array($participants)) {
            $res->getBody()->write(json_encode(['error' => 'El campo "participants" debe ser un arreglo']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }


        
        // obtiene la cantidad de participantes ingresados
        $cant_participantes = count($participants);

        //verifica que la cantidad de participantes que se ingreso sea 1 o 3 (simple o doble)
        if (!in_array($cant_participantes, [1, 3])) {//in_array(valor_a_buscar, array_donde_buscar)
            $res->getBody()->write(json_encode(['error' => 'Debe ingresar 1 o 3 participantes']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }


        //Verifica que todos los id ingresados sean enteros 
        foreach ($participants as $p) {
            if (!is_int($p)) {
                $res->getBody()->write(json_encode(['error' => 'los ID de los participantes deber ser números enteros']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }


        // verifica que los participantes no incluyan al creador 
        if (in_array($id_creador_reserva, $participants)) {
            $res->getBody()->write(json_encode(['error' => 'El creador no puede ser también participante']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Verificar que no haya participantes repetidos si son 3 --- en 1 participante no tiene sentido
        if (($cant_participantes) == 3) {

            if (($cant_participantes) !== count(array_unique($participants))) { //array_unique elimina los duplicados de un array
                // si la cantidad de participantes es distinta a la cantidad de participantes únicos, hay repetidos
                $res->getBody()->write(json_encode(['error' => 'Hay participantes repetidos']));
                return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400);
            }

        }

        $part_en_reserva = [];

        $pueden_part = [];

        
        foreach ($participants as $p) {

            //verifica que el participante exista
            if (!self::validarUsuario($p,$db) ){

                $res->getBody()->write(json_encode(['error' => "El participante con id {$p} no existe"]));     
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            //verifica que el participante este en la reserva
            if (self::estaEnLaReserva($bookingId,$p,$db)){
                $part_en_reserva[] = $p;
            }

            //verifica que el participante pueda participar
            if (self::puedeParticipar($p, $dia_hora_reserva, $duracion_reserva, $db)){
                $pueden_part [] = $p;
            }
        }

        //cuento la cantidad de paticipantes actual de la reserva
         //COUNT(*) para contar la cantidad de participantes actuales y llamo a ese campo total
        $sql = $db->prepare("SELECT COUNT(*) as total  FROM booking_participants WHERE booking_id = ?");
        $sql->execute([$bookingId]);
        $row = $sql->fetch(PDO::FETCH_ASSOC);
        //recordar que el hacer la consulta devuelve un array asociativo con los campos de la tabla
        //entonces para acceder al campo total hago $row['total'] y lo casteo a int
        $cant_total = (int)$row['total'];// deberia ser 2 o 3;

        if (($cant_total)-1 == count($part_en_reserva) && count($part_en_reserva)==$cant_participantes){
            $res->getBody()->write(json_encode(['error' => 'Todos los participantes ingresados ya está en la reserva']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(400); 
        }


        $part_a_agregar = array_merge($part_en_reserva,$pueden_part); //deveria devolver el vector ingresado

        if(count($part_a_agregar)== $cant_participantes){//los part ingresados pueden o ya son parte de la reserva

            //agreaga solo los participantes que no estan en la reserva 
            foreach ($pueden_part as $p){
                $insert = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?)");
                $insert->execute([$bookingId, $p]);
            }

            //elimino los participantes que ya no lo quiero
            $part_a_agregar [] = $id_creador_reserva; //agrego al creador para que al eliminar no lo tome en cueenta
             
            $placeholders = implode(',', array_fill(0, count($part_a_agregar), '?'));//pasa al vector a una cadena que entienda sql

            $sql = "DELETE FROM booking_participants WHERE booking_id = ? AND user_id NOT IN ($placeholders)";
            $stmt = $db->prepare($sql);
            // pasamos el booking_id primero, y luego los ids a mantener
            $stmt->execute(array_merge([$bookingId], $part_a_agregar));//

             //informo que fue exitoso
            $res->getBody()->write(json_encode(['success' => "Participantes de la reserva modificados, ",
                                                'participants' => $participants ]));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(200); 

        }else{
            $part_noDisponibles =[];
            foreach ($participants as $p){
                if (!in_array($p, ($part_a_agregar))){
                    $part_noDisponibles []= $p;
                }
            }

            $res->getBody()->write(json_encode(['error' => "los participantes no puede participar en la reserva",
                                                'participantes no disponibles' => $part_noDisponibles]));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(409);
        }



   } catch (PDOException $e) {
            // Captura errores de la DB y devuelve un mensaje JSON
            $res->getBody()->write(json_encode([
                'error' => 'Error al consultar la base de datos',
                'details' => $e->getMessage()  // opcional, útil en desarrollo
            ]));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
    

  }

}
