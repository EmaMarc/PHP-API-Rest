<?php

// DB imported

require_once __DIR__ . '/../Utils/db.php';

class Courts {
    
    public $id;
    public $name;
    public $description;
    
    // Get all users from the database
    public static function getAll(){
        $db = DB::getConnection();
        $stmt = $db->query("SELECT * FROM courts");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public static function crear($unName, $unaDescription) {
        $db = DB::getConnection();
        $stmt = $db->prepare("INSERT INTO courts (name, description) VALUES (?, ?)");
                    //el id no se pasa porqu es aunto incremental
        return $stmt->execute([$unName, $unaDescription]); //retorna F o V

//  $stmt->execute([$name, $description]);
//  return $db->lastInsertId(); // devuelve el id generad

    }

    public static function buscarPorNombre($unName) {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * from courts WHERE name = ?"); // ? porque la consulta la paso por parametro 
        return $stmt->execute([$unName]); //retorna F o V

        //return $stmt->fetchAll(PDO::FETCH_ASSOC); //retorna los resultados 
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

}