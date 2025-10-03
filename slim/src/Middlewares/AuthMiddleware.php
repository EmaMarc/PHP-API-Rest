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
    //extraigo el header de Authorization
    $auth = $request->getHeaderLine('Authorization');

    //stripos busca el bearer y si no lo encuentra devuelve false  
    if (stripos($auth, 'Bearer ') !== 0) {
      //si no empieza con Bearer, devuelvo error 401
      return $this->jsonError(401, ['error' => 'Falta o es inválido el header Authorization']);
    }

    //extraigo el token (quito "Bearer " del inicio)
    $token = trim(substr($auth, 7));
    if ($token === '') {
        return $this->jsonError(401, ['error' => 'Token vacío']);
    }

    $db  = \DB::getConnection();
    
    $token  = $db->quote($token);
    $sql = "SELECT id, is_admin
            FROM users
            WHERE token = $token
            AND expired > NOW()
            LIMIT 1";

    $row = $db->query($sql)->fetch(\PDO::FETCH_ASSOC);
    
    //si no encuentra el token, devuelvo error 401
    if (!$row) {
        return $this->jsonError(401, ['error' => 'Token inválido o vencido']);
    }

    $id = (int)$row['id'];
    $admin = (int)$row['is_admin'];
    //extiendo la expiración del token
    $sql = "UPDATE users
            SET expired = DATE_ADD(NOW(), INTERVAL {$this->ttl} SECOND)
            WHERE id = $id";

    $db->exec($sql);

    //le agrego al request el id y si es admin o no sin modificar el request original
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