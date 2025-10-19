<?php

 // DB imported
 

 namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';
require_once __DIR__ . '/../Utils/Authentication.php';


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use DateTime; //para verificar que las fechas tengan un formato correcto


final class BookingsModule{

    //verifica si el usuario existe en la base de datos
    private static function validarUsuario($id, $db) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? true : false; //retorna true si el usuario existe, false si no
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



    // POST /booking -------------------------------------------------------------------------------
    public static function crearReserva(Request $req, Response $res): Response {

        $auth = $req->getAttribute('auth_user');

        if (!$auth) {
            $res->getBody()->write(json_encode(['error' => 'No autorizado']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $id_creador = (int)($auth['id'] ?? 0);//el id del usuario creador de la reserva desde el token

        
        // ahora con body parsing middleware
        $data = $req->getParsedBody();
        
        if (!$data || !is_array($data)) {//verifica que se hayan recibido datos
            $res->getBody()->write(json_encode(['error' => 'No se recibieron datos válidos']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // extrae los campos ingresados
        $dia_hora_reserva = trim($data['booking_datetime'] ?? '');
        $cant_bloques = (int)($data['duration_blocks'] ?? 0);
        $court_id = (int)($data['court_id'] ?? 0);
        $participants = $data['participants'] ?? [];//vector con los id de los participantes

        // Validar que participantes sea un arreglo ejem que no sea 3 , "hola",  "3,4,5"
        if (!is_array($participants)) {
            $res->getBody()->write(json_encode(['error' => 'El campo "participants" debe ser un arreglo']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        //Verifica que todos los id ingresados sean enteros 
        foreach ($participants as $p) {
            if (!is_int($p)) {
                $res->getBody()->write(json_encode(['error' => 'los ID de los participantes deber ser números enteros']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }


        // obtiene la cantidad de participantes
        $cant_participantes = count($participants);

        //verifica que la cantidad de participantes sea 1 o 3
        if (!in_array($cant_participantes, [1, 3])) {//in_array(valor_a_buscar, array_donde_buscar)
            $res->getBody()->write(json_encode(['error' => 'Debe ingresar 1 o 3 participantes']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        

        // verifica que los participantes no incluyan al creador 
        if (in_array($id_creador, $participants)) {
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


        // verifica que la fecha este en un ofmato correcto Y-m-d H:i:s 
        $fechaObj = DateTime::createFromFormat('Y-m-d H:i:s', $dia_hora_reserva);
        if (!$fechaObj) {
            $res->getBody()->write(json_encode(['error' => 'Fecha inválida o mal formada']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // verifica que los bloques sean entre 1 y 6
        if ($cant_bloques < 1 || $cant_bloques > 6) {
            $res->getBody()->write(json_encode(['error' => 'La cantidad de bloques debe ser entre 1 y 6']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try{
            //se conecta a la db
            $db = \DB::getConnection();
            \Authentication::refreshToken($db, $auth['id'], 300);

            //verifica que la cancha exista
            $stmt = $db->prepare("SELECT * FROM courts WHERE id = ?");
            $stmt->execute([$court_id]);
            $court = $stmt->fetch(PDO::FETCH_ASSOC);//obtiene el resultado de la consulta

            if (!$court) {
                $res->getBody()->write(json_encode(['error' => 'Cancha ingresada no fue encontrada']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            // Verifica que la reserva no termine después de las 22:00
            $fechaObjFin = clone $fechaObj;
            $fechaObjFin->modify('+' . ($cant_bloques * 30) . ' minutes');
            $horaFin = (int)$fechaObjFin->format('H');
            $minFin = (int)$fechaObjFin->format('i');

            if ($horaFin > 22 || ($horaFin === 22 && $minFin > 0)) {
                $res->getBody()->write(json_encode(['error' => 'La reserva no puede terminar después de las 22:00']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            //verifica si la cancha esta disponible en el horario solicitado
            $stmt = $db->prepare("SELECT c.* 
                FROM courts c
                INNER JOIN bookings b ON b.court_id = c.id
                WHERE c.id = ?
                AND b.booking_datetime < ? 
                AND DATE_ADD(b.booking_datetime, INTERVAL b.duration_blocks * 30 MINUTE) > ?");
            $stmt->execute([$court_id, $fechaObjFin->format('Y-m-d H:i:s'), $fechaObj->format('Y-m-d H:i:s')]);
        
            // Si hay resultados, significa que ya tiene otra reserva en ese horario true (no puede participar)
            // Si no hay resultados, puede participar false
            $cancha_disponible = !($stmt->fetch(PDO::FETCH_ASSOC));//lo niega si no hay resultados true (puede participar) 
                                                  // si hay resultados false (no puede participar)

            if (!$cancha_disponible) {
                $res->getBody()->write(json_encode(['error' => 'La cancha no está disponible en el horario solicitado']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            //por las dudas verifico que el id del creador no este vacio
            if (empty($id_creador)) {
                $res->getBody()->write(json_encode(['error' => 'No se encontró el id del creador de la reserva']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            //verifique que el creador pueda participar en la reserva
            if (!self::puedeParticipar($id_creador, $dia_hora_reserva, $cant_bloques, $db)) {
                $res->getBody()->write(json_encode(['error' => 'El creador ya tiene una reserva que se cruza con la nueva']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            //verifico que cada participante exista y pueda participar en la reserva
            foreach ($participants as $pid) { //$pid toma el valor de cada elemento del array participants

                //verifico que el usuario participante exista
                if (!self::validarUsuario($pid, $db)) {
                    $res->getBody()->write(json_encode(['error' => "El participante con id {$pid} no existe"])); 
                    
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                //verifico que el usuario participante pueda participar en la reserva
                if (!self::puedeParticipar($pid, $dia_hora_reserva, $cant_bloques, $db)) {
                    $res->getBody()->write(json_encode(['error' => "El participante con id {$pid} ya tiene una reserva que se cruza con la nueva"]));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(409);
                }

                
            }

            //despues de todas las validaciones, creo la reserva 
            $stmt = $db->prepare("INSERT INTO bookings (booking_datetime, duration_blocks, court_id, created_by)
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$dia_hora_reserva, $cant_bloques, $court_id, $id_creador]);
            $id_denueva_reserva = $db->lastInsertId();

            // Insertar relaciones en booking_participants
            $stmt = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) VALUES (?, ?)");
            foreach ($participants as $pid) {
                $stmt->execute([$id_denueva_reserva, $pid]);
            }
            // Agregar el creador también como participante
            $stmt->execute([$id_denueva_reserva, $id_creador]);

            $mensaje = ($cant_participantes === 1) 
                ? 'Reserva creada (1 vs 1)' 
                : 'Reserva creada (2 vs 2)';

            $res->getBody()->write(json_encode([
                'success' => $mensaje,
                'booking_id' => $id_denueva_reserva
            ]));

            return $res->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\Throwable $e) {
            //  \Throwable es la interfaz base de todo lo que puede ser lanzado con throw
            // y capturado con catch. engloba tanto a las excepciones (Exception) como a los errores fatales (Error).
            error_log($e);
            $res->getBody()->write(json_encode(['error' => 'Error interno']));  
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
        }
    }



    // DELETE /booking/{id} -------------------------------------------------------------------------------
    public static function eliminar(Request $req, Response $res, array $args): Response {
        
        $id = (int)($args['id']);//id desde la ruta y verifica que sea un nro

        if (!$id) {//
            
            $res->getBody()->write(json_encode(['error' => 'Falta el ID de la reserva']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

         try{

            $db = \DB::getConnection();



            //verifico si la reserva existe 
            $sql = $db->prepare ("SELECT * FROM bookings 
                                    WHERE id = ?");

            $sql->execute([$id]); //retorna F o V
            $row = $sql->fetchAll(PDO::FETCH_ASSOC);

            //me quedo con el id del creador de la reserva
            $id_creador = $row[0]['created_by'] ?? 0;
            //aca verifico solo lo el creador de la reserva puede eliminarla o el admin
            // is_admin desde el token (validado por el middleware)
            $auth = $req->getAttribute('auth_user');
            //pregunto si esta autorizado y le paso auth y id del usuario a modificar
            if (!\Authentication::tienePermiso($auth, $id_creador)) {
                $res->getBody()->write(json_encode(['error' => 'Usuario No autorizado']));
                return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                    ->withStatus(401);
            }
            /*else{ //para probar
                $res->getBody()->write(json_encode(['ok' => 'Usuario autorizado']));
                return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                    ->withStatus(200);
            }*/

            //si el token es valido, refresco su expiración
            \Authentication::refreshToken($db, $auth['id'], 300);

            if (!empty($row)){ //la reserva existe 

                //elimino los usuarios asociados a la reserva
                $elim_part = $db->prepare ("DELETE FROM booking_participants WHERE booking_id = ?");
                $elim_part->execute([$id]); //retorna F o V
                //elimino la reserva
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
        } catch (\Throwable $e) {
            //  \Throwable es la interfaz base de todo lo que puede ser lanzado con throw
            // y capturado con catch. engloba tanto a las excepciones (Exception) como a los errores fatales (Error).
            error_log($e);
            $res->getBody()->write(json_encode(['error' => 'Error interno']));  
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
        }



    }

// GET /booking?date={date} -------------------------------------------------------------------------------
    public static function reservas(Request $req, Response $res): Response {
        //query params lo que esta despues del ?    /booking?date={date}
        $queryParams = $req->getQueryParams(); // devuelve un array asociativo con los query params


        $date = $queryParams['date'] ?? null;


        try{
            $db = \DB::getConnection();

            //si la fecha esta vacia muestro todas las reservas
            if (!$date) {
            
                $sql = $db->query("SELECT b.id, b.booking_datetime, b.duration_blocks, c.name AS court_name, c.description AS court_description 
                   FROM bookings b  
                   INNER JOIN courts c ON b.court_id = c.id
                   ORDER BY c.name, b.booking_datetime");
                $row = $sql->fetchAll(PDO::FETCH_ASSOC);


                if (!($row)){
                    $res->getBody()->write(json_encode(['error' => 'No se encontraron reservas']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(404);

                }else {

                    //devuelve todas las reservas encontradas
                    $res->getBody()->write(json_encode($row));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(200);
                }

             }
         
             //  Validar que la fecha tenga el formato correcto y sea válida
         
             //Intenta parsear el string $date según el formato 'Y-m-d', si no puede devuelve false
             $dateObj = DateTime::createFromFormat('Y-m-d', $date);
             //$dateObj pordria devolver un obj por eso se chequea errores
             $errors = DateTime::getLastErrors() ?: ['warning_count' => 0, 'error_count' => 0];//si es null asigna un array vacio
         
             //si no pude crar el obnjeto fecha o si hubo errores o advertencias  
             if (!$dateObj || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {

                 $res->getBody()->write(json_encode(['error' => 'Fecha inválida o mal formada']));
                 return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
             }

            //verifico si la reserva existe 
            $sql = $db->prepare ("SELECT b.id, b.booking_datetime, b.duration_blocks, c.name AS court_name, c.description AS court_description 
                                FROM bookings b  INNER JOIN courts c ON b.court_id = c.id
                                WHERE DATE(booking_datetime) = ?
                                ORDER BY c.name, b.booking_datetime ");//date toma solo el dia
            $sql->execute([$date]); //retorna F o V
            $row = $sql->fetchAll(PDO::FETCH_ASSOC); //devuelve todos los resultados

            if (!($row)){
                $res->getBody()->write(json_encode(['error' => 'No se encontró reservas para ese dia']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(404);

            }else{

                //devuelve las reservas encontradas de ese dia 
                $res->getBody()->write(json_encode($row));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(200);
            }
        } catch (\Throwable $e) {
            //  \Throwable es la interfaz base de todo lo que puede ser lanzado con throw
            // y capturado con catch. engloba tanto a las excepciones (Exception) como a los errores fatales (Error).
            error_log($e);
            $res->getBody()->write(json_encode(['error' => 'Error interno']));  
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
        }
        

    }

}