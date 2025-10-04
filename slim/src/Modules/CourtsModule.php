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
        $db = DB::getConnection();
        $stmt = $db->query("SELECT * FROM courts");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

/*
    public static function createCourts(Request $req, Response $res){
        $data = $req->getParsedBody(); 
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? null; // puede ser nulo

        $db = DB::getConnection();

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


    public static function buscarPorId($unId) {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * from courts WHERE id = ?"); // ? porque la consulta la paso por parametro 
        $stmt->execute([$unId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC); //retorna los resultados 
    }

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
    $id = $args['id'] ?? null;
    
    // Si no se pasó el id
    if (!$id || !is_numeric($id)) {
        
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