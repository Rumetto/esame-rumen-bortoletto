<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function isConfigured(): bool
    {
        return self::env('DB_HOST') !== null
            && self::env('DB_NAME') !== null
            && self::env('DB_USER') !== null;
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = self::requiredEnv('DB_HOST');
        $port = self::env('DB_PORT') ?? '3306';
        $name = self::requiredEnv('DB_NAME');
        $user = self::requiredEnv('DB_USER');
        $password = self::env('DB_PASSWORD') ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        self::$connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }

    private static function requiredEnv(string $name): string
    {
        return self::env($name)
            ?? throw new \RuntimeException("Variabile d'ambiente {$name} non configurata");
    }

    private static function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? getenv($name);

        return $value === false || $value === '' ? null : $value;
    }
}
