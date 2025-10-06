<?php

 // DB imported
 

 namespace App\Modules;

require_once __DIR__ . '/../Utils/db.php';



use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;


final class CourtsModule{

    // Get all users from the database
    public static function getAll(){
        $db = \DB::getConnection();
        
        $stmt = $db->query("SELECT * FROM courts");
        $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $res->getBody()->write(json_encode($row));
        return $res->withHeader('Content-Type', 'application/json')->withStatus(200);
    }


    public static function createCourts(Request $req, Response $res): Response {

        $body = $req->getBody();
        $data = json_decode($body, true); // true para que devuelva array asociativo
        
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? null; // puede ser nulo

        if(empty($name)) {
            $res->getBody()->write(json_encode(['error' => 'El nombre es obligatorio']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = \DB::getConnection();

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
    }

    public static function editar(Request $req, Response $res, array $args): Response{
        $id = (int)($args['id']);//id desde la ruta y verifica que sea un nro

        if (!$id) {
            
            $res->getBody()->write(json_encode(['error' => 'Falta el ID de la cancha']));
            return $res->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $db = \DB::getConnection();

        //  Verifico si ya existe una cancha con ese id
        $stmt = $db->prepare("SELECT * FROM courts WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($row) { // si existe
            
            $body = $req->getBody();
            $data = json_decode($body, true); // true para que devuelva array asociativo
            
            //tomo los valores a modificar 
            //$data = $req->getParsedBody(); 
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

    }

    /*
    //que campos se pueden editar ?? ambos o solo uno 
    //aca se sebe ir contruyendo la consulta mientras voy preguntando que campo no esta vacio porque se puede modificar un campo o el otro o ambos campos
    public static function editar($unId, $unName, $unaDescription){
        $db = DB::getConnection();
        $stmt = $db->prepare("UPDATE courts SET name = ? description = ? WHERE id = ?");
        
        return $stmt->execute([$unName, $unaDescription,$unId]); //retorna F o V
        
    }




    public static function eliminar($unId){//elimina solo si no tiene reservas
        $db = DB::getConnection();
        //esto lo separo en dos consultas literal, primero si tiene un reserva (asi si npo la tiene el error es mas especifico), y con ese resultado de la consulta hacer (o no) el eliiminar
        
        $stmt = $db->prepare("SELECT * FROM booking WHERE court_id = ? ");
        $stmt->execute([$unId]);//retorna F o V
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($res)){ //si lo elim devuelve v o f
            $elim = $db->prepare ("DELETE FROM courts WHERE id = ?");
            return $elim->execute([$unId]); //retorna F o V
        }else {
            //sino lo pudo elim devuelve las resevas que tiene
            return $res;//
        }
        
        
    }

*/



    public static function getCourtsById(Request $req, Response $res, array $args): Response {
        // PHP controla que devuelva un objeto Response
        $id = (int)($args['id']);//id desde la ruta y verifica que sea un nro
        //si no es un nro le pone 0 y php toma al cero como vacio, por eso los id comienzan en 1 
        
        // Si no se pasó el id 
        if (!$id) {
            
            $res->getBody()->write(json_encode(['error' => 'Falta el ID de la cancha']));
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