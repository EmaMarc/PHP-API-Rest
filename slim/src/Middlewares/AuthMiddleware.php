<?php
namespace App\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;


final class AuthMiddleware implements MiddlewareInterface {
  private int $ttl; //ttl = time to live (segundos que dura el token)
  
  //cada vez que se instancia el middleware, se extiende el tiempo de vida del token
  public function __construct(int $ttlSeconds = 300) {
    $this->ttl = $ttlSeconds;
  }

  public function process(Request $request, Handler $handler): Response {
    // Extraigo el header Authorization
    $auth = $request->getHeaderLine('Authorization');
    if (stripos($auth, 'Bearer ') !== 0) {
        return $this->jsonError(401, ['error' => 'Falta o es inválido el header Authorization']);
    }
    
    // Extraigo y limpio el token
    $token = trim(substr($auth, 7));
    if ($token === '') {
        return $this->jsonError(401, ['error' => 'Token vacío']);
    }
    
    // ELIMINADO: ini_set, error_reporting, var_dump y die()
    // Si quieres debuggear, usa error_log() en lugar de var_dump + die
    // error_log("Auth header: " . $auth);
    // error_log("Token extraído: " . $token);
    
    $db = \DB::getConnection();
    
    // Consulta usando TRIM + prepare
    $sql = "SELECT id, is_admin
            FROM users
            WHERE TRIM(token) = ?
            AND expired > NOW()
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$token]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$row) {
        return $this->jsonError(401, ['error' => 'Token inválido o vencido']);
    }
    
    $id = (int)$row['id'];
    $admin = (int)$row['is_admin'];
    
    // Extiendo expiración del token
    $sql = "UPDATE users
            SET expired = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$this->ttl, $id]);
    
    // Inyecto datos al request
    $request = $request->withAttribute('auth_user', [
        'id'       => $id,
        'is_admin' => $admin,
    ]);
    
    return $handler->handle($request);
}


  private function jsonError(int $status, array $payload): Response {
    $res = new SlimResponse($status);
    $res->getBody()->write(json_encode($payload));
    return $res->withHeader('Content-Type', 'application/json; charset=utf-8');
  }

}