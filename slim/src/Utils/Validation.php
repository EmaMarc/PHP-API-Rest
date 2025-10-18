<?php
namespace App\Utils;

final class Validation {
    private function __construct() {} // Evita instanciación por que es esta clase solo tiene métodos estáticos

    public static function isValidEmail(string $email): bool
    {
        // Normalizo
        $email = trim($email);

        // Regex: usuario@dominio.tld
        $pattern = '/^(?=.{1,64}@)[A-Z0-9._%+\-]+(?:\.[A-Z0-9._%+\-]+)*@(?:[A-Z0-9-]+\.)+[A-Z]{2,}$/i';

        if (preg_match($pattern, $email) !== 1) return false;

        // Guardas extra: evitar puntos consecutivos y puntos al inicio/fin
        if (strpos($email, '..') !== false) return false;

        [$local, $domain] = explode('@', $email, 2);
        if ($local[0] === '.' || substr($local, -1) === '.') return false;
        if ($domain[0] === '.' || substr($domain, -1) === '.') return false;

        return true;
    }

//----------------------------------------------------------------------------
    // Reglas atómicas de contraseña

    public static function hasMinLength(string $pwd, int $min = 8): bool {
        return mb_strlen($pwd) >= $min;
    }

    public static function hasUpper(string $pwd): bool {
        return (bool) preg_match('/[A-Z]/', $pwd);
    }

    public static function hasLower(string $pwd): bool {
        return (bool) preg_match('/[a-z]/', $pwd);
    }

    public static function hasDigit(string $pwd): bool {
        return (bool) preg_match('/\d/', $pwd);
    }

    public static function hasSpecial(string $pwd): bool {
        return (bool) preg_match('/[^a-zA-Z0-9]/', $pwd);
    }

    
     // Valida TODAS las reglas
    public static function validatePassword(string $pwd): array {
        $errors = [];

        // Normalizo
        $pwd = (string) $pwd;

        // Reglas
        if (!self::hasMinLength($pwd, 8))   $errors[] = 'Debe tener al menos 8 caracteres';
        if (!self::hasUpper($pwd))          $errors[] = 'Debe incluir al menos una letra mayúscula';
        if (!self::hasLower($pwd))          $errors[] = 'Debe incluir al menos una letra minúscula';
        if (!self::hasDigit($pwd))          $errors[] = 'Debe incluir al menos un número';
        if (!self::hasSpecial($pwd))        $errors[] = 'Debe incluir al menos un carácter especial';

        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }
        return ['ok' => true];
    }
}
