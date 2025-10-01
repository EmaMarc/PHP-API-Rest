<?php

class db {
    private static $connection;

    public static function getConnection() {
        if (!self::$connection) {
            $host = 'db';
            $dbname = 'seminariophp';
            $user = 'seminariophp';
            $pass = 'seminariophp';

            try {
                self::$connection = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die(json_encode(['error' => $e->getMessage()]));
            }
        }

        return self::$connection;
    }
}
