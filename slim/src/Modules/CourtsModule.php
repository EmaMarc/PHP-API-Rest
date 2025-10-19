<?php

 // DB imported
 

 namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';
require_once __DIR__ . '/../Utils/Authentication.php';



use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;


final class CourtsModule{

    

    // POST /court -----------------------------------------------------------------------------------------------
    public static function createCourts(Request $req, Response $res): Response {

        // is_admin desde el token (validado por el middleware)
        $auth = $req->getAttribute('auth_user');
        //pregunto si esta autorizado y le paso auth y id del usuario a modificar
        if (!\Authentication::isAdmin($auth)) {
            $res->getBody()->write(json_encode(['error' => 'No autorizado']));
            return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                ->withStatus(401);
        }

        // ahora con body parsing middleware
        $data = $req->getParsedBody();
        
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? null; // puede ser nulo

        if(empty($name)) {
            $res->getBody()->write(json_encode(['error' => 'El nombre es obligatorio']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $db = \DB::getConnection();

            //si el token es valido, refresco su expiración
            \Authentication::refreshToken($db, $auth['id'], 300);

            //  Verifico si ya existe una cancha con ese nombre
            $stmt = $db->prepare("SELECT * FROM courts WHERE name = ?");
            $stmt->execute([$name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) { // si no existe
                // Inserto la nueva cancha
                $insert = $db->prepare("INSERT INTO courts (name, description) VALUES (?, ?)");
                $insert->execute([$name, $description]);

                //  Devuelvo la respuesta
                $res->getBody()->write(json_encode(['success' => 'Cancha creada correctamente']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(201);
            
            } else {
            
                $res->getBody()->write(json_encode(['error' => 'Ya existe una cancha con ese nombre']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }


        } catch (\Throwable $e) {
        //  \Throwable es la interfaz base de todo lo que puede ser lanzado con throw y capturado con catch.
            //engloba tanto a las excepciones (Exception) como a los errores fatales (Error).

          error_log($e);
          $res->getBody()->write(json_encode(['error' => 'Error interno']));
          return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
        }
        
    }

    // PUT /court/{id} -----------------------------------------------------------------------------------------------
    public static function editar(Request $req, Response $res, array $args): Response{
        $id = (int)($args['id']);//id desde la ruta y verifica que sea un nro

        if (!$id) {
            
            $res->getBody()->write(json_encode(['error' => 'Falta el ID de la cancha']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // is_admin desde el token (validado por el middleware)
        $auth = $req->getAttribute('auth_user');
        //pregunto si esta autorizado y le paso auth y id del usuario a modificar
        if (!\Authentication::isAdmin($auth)) {
            $res->getBody()->write(json_encode(['error' => 'No autorizado']));
            return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                ->withStatus(401);
        }

        try{

            $db = \DB::getConnection();

            //si el token es valido, refresco su expiración
            \Authentication::refreshToken($db, $auth['id'], 300);

            //  Verifico si ya existe una cancha con ese id
            $stmt = $db->prepare("SELECT * FROM courts WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);


            if ($row) { // si existe

                // ahora con body parsing middleware
                $data = $req->getParsedBody();

                
                $name = $data['name'] ?? '';
                $description = $data['description'] ?? null; // puede ser nulo

                // no hay campos por modificar 
                if (empty($name) && empty($description)) {
                    $res->getBody()->write(json_encode(['error' => 'Debe enviar al menos un campo para modificar']));
                    return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
                }

                if (empty($name) || empty($description)){//si solo tengo que modificar alguno de los campos

                    if (empty($name) && !empty($description)){ //solo midifico descripcion

                        $insert = $db->prepare("UPDATE courts SET description = ? WHERE id = ?");
                        $insert->execute([$description, $id]);
                        $row = $insert->fetch(PDO::FETCH_ASSOC);
                    
                    }else{//solo midifico el nombre

                        $insert = $db->prepare("UPDATE courts SET name = ? WHERE id = ?");
                        $insert->execute([$name, $id]);
                        $row = $insert->fetch(PDO::FETCH_ASSOC);
                    }

                }else{ //modifico lo dos campos 
                    $insert = $db->prepare("UPDATE courts SET name = ?, description = ? WHERE id = ?");
                    $insert->execute([$name, $description, $id]);
                    $row = $insert->fetch(PDO::FETCH_ASSOC);
                }

                //  Devuelvo la respuesta
                $res->getBody()->write(json_encode(['success' => 'Cancha modificada correctamente']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(201);
            
            } else { //si no existe
            
                $res->getBody()->write(json_encode(['error' => 'No existe una cancha con ese ID']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        } catch (\Throwable $e) {
            //  \Throwable es la interfaz base de todo lo que puede ser lanzado con throw y capturado con catch.
            //engloba tanto a las excepciones (Exception) como a los errores fatales (Error).   
            error_log($e);  
            $res->getBody()->write(json_encode(['error' => 'Error interno']));
            return $res->withHeader('Content-Type','application/json; charset=utf-8')->withStatus(500);
        }       

    }

    // DELETE /court/{id} -----------------------------------------------------------------------------------------------
    public static function eliminar(Request $req, Response $res, array $args): Response {
        $id = (int)($args['id']);
        
        
        

        if (!$id) { //id vacio o no numerico 
            
            $res->getBody()->write(json_encode(['error' => 'Falta el ID de la cancha']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // is_admin desde el token (validado por el middleware)
        $auth = $req->getAttribute('auth_user');
        //pregunto si esta autorizado y le paso auth y id del usuario a modificar
        if (!\Authentication::isAdmin($auth)) {
            $res->getBody()->write(json_encode(['error' => 'No autorizado']));
            return  $res->withHeader('Content-Type','application/json; charset=utf-8')
                ->withStatus(401);
        }
        
        try{
            $db = \DB::getConnection();

            //si el token es valido, refresco su expiración
            \Authentication::refreshToken($db, $auth['id'], 300);

            //busco si existe la cancha
            $stmt = $db->prepare("SELECT * FROM courts WHERE id = ?");
            $stmt->execute([$id]);//retorna F o V
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!($row)){ //si no existe la cancha
                $res->getBody()->write(json_encode(['error' => 'No existe la cancha']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(404);
            }



            //busco si tiene reservas
            $stmt = $db->prepare("SELECT * FROM bookings WHERE court_id = ? ");
            $stmt->execute([$id]);//retorna F o V
            $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

            //si res es vacio no hay reservas
            if (empty($row)){ //si lo elim devuelve v o f

                $elim = $db->prepare ("DELETE FROM courts WHERE id = ?");
                $elim->execute([$id]); //retorna F o V

                $res->getBody()->write(json_encode(['ok' => true, 'deleted_id' => $id]));
                return $res->withHeader('Content-Type','application/json; charset=utf-8');


            }else {
                //sino lo pudo elim devuelve las resevas que tiene
                $res->getBody()->write(json_encode(['error' => 'La cancha tiene reservas']));
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


    // GET /court/{id} -----------------------------------------------------------------------------------------------
    public static function getCourtsById(Request $req, Response $res, array $args): Response {
        // PHP controla que devuelva un objeto Response
        $id = (int)($args['id']);//id desde la ruta y verifica que sea un nro
        //si no es un nro le pone 0 y php toma al cero como vacio, por eso los id comienzan en 1 
        
        // Si no se pasó el id 
        if (!$id) {
            
            $res->getBody()->write(json_encode(['error' => 'Falta el ID de la cancha']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if ($id<= 0) {
            
            $res->getBody()->write(json_encode(['error' => 'ID de cancha no es válido']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    
        try {
            // Conexión a la base de datos
            $db = \DB::getConnection();
            
            // Buscar la cancha por ID
            $stmt = $db->prepare("SELECT * FROM courts WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
            // Si no se encontró la cancha
            if (!($row)) {
                $res->getBody()->write(json_encode(['error' => 'No se encontró la cancha']));
                return $res->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        
            // Si se encontró, devolver los datos
            $res->getBody()->write(json_encode($row));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(200);
        
            
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