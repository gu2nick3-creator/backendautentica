<?php
declare(strict_types=1);
namespace App\Core;
use PDO; use PDOException; use RuntimeException;

class Database {
    private static ?PDO $connection = null;
    public static function connection(): PDO {
        if (self::$connection) return self::$connection;
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST','127.0.0.1'), env('DB_PORT','3306'), env('DB_NAME',''));
        try {
            self::$connection = new PDO($dsn, env('DB_USER',''), env('DB_PASSWORD',''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Erro ao conectar no banco: ' . $e->getMessage());
        }
        return self::$connection;
    }
}
