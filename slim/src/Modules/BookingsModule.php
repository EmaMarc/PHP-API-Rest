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
            return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                ->withStatus(401);
        }  

        $id_creador = (int)$auth['id'] ?? 0; //id del usuario que crea la reserva (deberia venir del token)


        $body = $req->getBody();
        $data = json_decode($body, true); // true para que devuelva array asociativo

        //desde el body se esperan estos campos los id de los compañeros elegidos, dia y hora (en un solo campo)
        //cantidad de bloques (30 minutos cada uno)(que no supere 6 ose 3hs) y que no se ecesa de las 22hs y el id de la cancha
        
        $id_new_1 = (int)$data['new_participants_1'] ?? '' ;//id del nuevo participante 1
        $id_new_2 = (int)$data['new_participants_2'] ?? '' ;//id del nuevo participante 2
        $id_new_3 = (int)$data['new_participants_3'] ?? '' ;//id del nuevo participante 3
        //trim quita espacios en blanco al inicio y al final de la cadena
        $dia_hora_reserva = trim($data['booking_datetime'] ?? '') ;//dia y hora de la reserva
        $cant_bloques = (int)$data['duration_blocks'] ?? 0 ;//cantidad de bloques (30 minutos cada uno)
        $court_id = (int)$data['court_id'] ?? 0 ;//id de la cancha
    
    //verifica el formato de la fecha y que sea valida
        //$fechaObj = new DateTime($dia_hora_reserva);//crea un objeto DateTime a partir de la cadena de fecha y hora
        
        //Intenta parsear el string $date según el formato 'Y-m-d', si no puede devuelve false
        $fechaObj = DateTime::createFromFormat('Y-m-d H:i:s', $dia_hora_reserva);
        //$dateObj pordria devolver un obj por eso se chequea errores
        //$errors = DateTime::getLastErrors();//Recupera un array con información sobre la última operación de parseo


        if (!$fechaObj) {
            // fecha mal formada
            $res->getBody()->write(json_encode(['error' => 'Fecha inválida o mal formada']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        //avisa si hay errores o advertencias en el parseo
        //$errors = DateTime::getLastErrors();
        //if ($errors['warning_count'] > 0 || $errors['error_count'] > 0) {
        //    $res->getBody()->write(json_encode(['error' => 'Fecha inválida o mal formada']));
        //    return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        //}
    //verifica que la cantidad de bloques sea entre 1 y 6 
        if ($cant_bloques < 1 || $cant_bloques > 6) {
            $res->getBody()->write(json_encode(['error' => 'La cantidad de bloques debe ser entre 1 y 6']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

    //verifica que la cancha elegida exista
        $db = \DB::getConnection();
        //si el token es valido, refresco su expiración
        \Authentication::refreshToken($db, $auth['id'], 300);


        $stmt = $db->prepare("SELECT * FROM courts WHERE id = ?");
        $stmt->execute([$court_id]);
        $court = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$court) {//la cancha no existe
            $res->getBody()->write(json_encode(['error' => 'Cancha ingresada no fue encontrada']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }


    // Verifico que la reserva no exceda las 22:00 horas
        $fechaObjFin = clone $fechaObj; // clonamos para no modificar la original
        $fechaObjFin->modify('+' . ($cant_bloques * 30) . ' minutes'); //le sumo la cantidad de bloques en minutos 

        $horaFin = (int)$fechaObjFin->format('H');
        $minFin = (int)$fechaObjFin->format('i');
            
        if ($horaFin > 22 || ($horaFin === 22 && $minFin > 0)) {
            $res->getBody()->write(json_encode(['error' => 'La reserva no puede terminar después de las 22:00']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }



    // Verifico que la cancha esté disponible en el horario solicitado
        $stmt = $db->prepare("SELECT c.* 
            FROM courts c
            INNER JOIN bookings b ON b.court_id = c.id
            WHERE c.id = ?
            AND b.booking_datetime < ? 
            AND DATE_ADD(b.booking_datetime, INTERVAL b.duration_blocks * 30 MINUTE) > ?");  

        $stmt->execute([$court_id,$fechaObjFin->format('Y-m-d H:i:s'), $fechaObj->format('Y-m-d H:i:s')]);
        //([$fechaObj->format('Y-m-d H:i:s')]); porque fechaObj es un objeto y hay que formatearlo a string


        // Si hay resultados, significa que ya tiene otra reserva en ese horario true (no puede participar)
        // Si no hay resultados, puede participar false
        $cancha_disponible = !($stmt->fetch(PDO::FETCH_ASSOC));//lo niega si no hay resultados true (puede participar) 
                                                  // si hay resultados false (no puede participar)

        if (!$cancha_disponible) {
            $res->getBody()->write(json_encode(['error' => 'La cancha no está disponible en el horario solicitado']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }



    //verifico si el creador puede participar en la reserva (si no tiene otra reserva que se cruce con la nueva)
        if (empty($id_creador)) {//verifico que el id del creador venga en el token
            $res->getBody()->write(json_encode(['error' => 'no se encontro el id del creador de la reserva']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);

        }
        if (!self::puedeParticipar($id_creador, $dia_hora_reserva, $cant_bloques, $db)) {
            $res->getBody()->write(json_encode(['error' => 'El usuario creador ya tiene una reserva que se cruza con la nueva']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(409);
        }

        //solo un participante es single (uno vs uno) recordar que el creador tambien es un participante
        if (!empty($id_new_1) && empty($id_new_2) && empty($id_new_3)) {

            //verifico que el id que se ingreso no sea el mismo que el del creador
            if ($id_new_1 === $id_creador) {
                $res->getBody()->write(json_encode(['error' => 'El participante 1 no puede ser el mismo que el creador de la reserva']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            //si solo se paso un id de participante
            if (!self::validarUsuario($id_new_1, $db)) {//self es parecido al this en java
                $res->getBody()->write(json_encode(['error' => 'El usuario participante 1 no existe']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if (!self::puedeParticipar($id_new_1, $dia_hora_reserva, $cant_bloques, $db)) {
                $res->getBody()->write(json_encode(['error' => 'El usuario participante 1 ya tiene una reserva que se cruza con la nueva']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(409);
            }

            //creo la reserva   
            $stmt = $db->prepare("INSERT INTO bookings (booking_datetime, duration_blocks, court_id, created_by) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$dia_hora_reserva, $cant_bloques, $court_id, $id_creador]);
            $id_denueva_reserva = $db->lastInsertId();

            //creo la relacion entre la reserva creada y el usuario seloeccionado
            $stmt = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) 
                                  VALUES (?, ?),
                                         (?, ?)");
            $stmt->execute([$id_denueva_reserva, $id_new_1,
                            $id_denueva_reserva, $id_creador]);       

            $res->getBody()->write(json_encode(['success' => 'Reserva creada con un participante', 'booking_id' => $id_denueva_reserva]));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(201);   
            
        }else{
            //tres participantes dobles (dos vs dos)  recordar que el creador tambien es un participante
            if (!empty($id_new_1) && !empty($id_new_2) && !empty($id_new_3)) {

                //verifico que los id que se ingreso no sea el mismo que el del creador
                if ($id_new_1 === $id_creador || $id_new_2 === $id_creador || $id_new_3 === $id_creador) {
                    $res->getBody()->write(json_encode(['error' => 'El participante  no puede ser el mismo que el creador de la reserva']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                //verifico que los id que se ingreso no sean iguales entre si
                if ($id_new_1 === $id_new_2 || $id_new_1 === $id_new_3 || $id_new_2 === $id_new_3) {
                    $res->getBody()->write(json_encode(['error' => 'Los participantes no pueden ser el mismo usuario entre si']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                //si se pasaron los 3 id de participantes
                if (!self::validarUsuario($id_new_1, $db)) {//self es parecido al this en java
                    $res->getBody()->write(json_encode(['error' => 'El usuario participante 1 no existe']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                if (!self::validarUsuario($id_new_2, $db)) {//self es parecido al this en java
                    $res->getBody()->write(json_encode(['error' => 'El usuario participante 2 no existe']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
                }
                if (!self::validarUsuario($id_new_3, $db)) {//self es parecido al this en java
                    $res->getBody()->write(json_encode(['error' => 'El usuario participante 3 no existe']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                if (!self::puedeParticipar($id_new_1, $dia_hora_reserva, $cant_bloques, $db)) {
                    $res->getBody()->write(json_encode(['error' => 'El usuario participante 1 ya tiene una reserva que se cruza con la nueva']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(409);
                }
                if (!self::puedeParticipar($id_new_2, $dia_hora_reserva, $cant_bloques, $db)) {
                    $res->getBody()->write(json_encode(['error' => 'El usuario participante 2 ya tiene una reserva que se cruza con la nueva']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(409);
                }
                if (!self::puedeParticipar($id_new_3, $dia_hora_reserva, $cant_bloques, $db)) {
                    $res->getBody()->write(json_encode(['error' => 'El usuario participante 3 ya tiene una reserva que se cruza con la nueva']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(409);
                }

                 //creo la reserva   
                $stmt = $db->prepare("INSERT INTO bookings (booking_datetime, duration_blocks, court_id, created_by) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([$dia_hora_reserva, $cant_bloques, $court_id, $id_creador]);
                $id_denueva_reserva = $db->lastInsertId();
                
                //creo la relacion entre la reserva creada y el usuario seloeccionado
                //y tambien la relacion del creador con la reserva porque es un participante mas
                $stmt = $db->prepare("INSERT INTO booking_participants (booking_id, user_id) 
                                      VALUES (?, ?),
                                             (?, ?),
                                             (?, ?),
                                             (?, ?)");
                $stmt->execute([$id_denueva_reserva, $id_new_1,
                                $id_denueva_reserva, $id_new_2,
                                $id_denueva_reserva, $id_new_3,
                                $id_denueva_reserva, $id_creador]);       
                
                $res->getBody()->write(json_encode(['success' => 'Reserva creada con un participante', 'booking_id' => $id_denueva_reserva]));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(201); 

            }
        }
        //si no se cumple ninguna de las dos condiciones anteriores
        $res->getBody()->write(json_encode(['error' => 'Debe ingresar un participante (1 vs 1) o tres participantes (2 vs 2)']));
        return $res->withHeader('Content-Type', 'application/json')->withStatus(400);   


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
        $sql = $db->prepare ("SELECT * FROM bookings 
                                WHERE id = ?");
                                    //funcionna pero ver xq no muentra los nobres de la cancha
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