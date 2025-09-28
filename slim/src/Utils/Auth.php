<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/Utils/DB.php';

final class Auth {
  public static function makeToken(): string {
    //md5 hashea, uniqid genera un id unico, mt_rand genera un numero random, se devuelve un string de 32 caracteres hexadecimal 
    return md5(uniqid((string)mt_rand(), true));
  }
  public static function hashPassword(string $plain): string {
    //md5 hashsea la contraseña ($plain = plana) y la guarda  
    return md5($plain); 
  }
  public static function verifyPassword(string $plain, string $hashed): bool {
    //compara la contraseña plana con la hasheada
    return md5($plain) === $hashed;
  }
}