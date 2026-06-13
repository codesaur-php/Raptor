<?php

namespace Raptor;

/**
 * Class DatabaseConnection
 *
 * Raptor Framework-ийн өгөгдлийн сангийн холболтыг нэг газраас удирдах
 * helper класс. HTTP entry point (public_html/index.php) болон тестүүд
 * бүгд энэ нэг газраас PDO авна.
 *
 * Driver сонголт `DRIVER` тогтмолоор хийгдэнэ ('mysql' | 'pgsql').
 * Тухайн системд DB сонгохдоо энэ тогтмолыг л өөрчилнө - өөр газар
 * давтан тохируулах шаардлагагүй.
 *
 * @package Raptor
 */
final class DatabaseConnection
{
    /**
     * Тохируулсан DB driver-г .env-аас унших.
     *
     * Боломжит утга: 'mysql' | 'pgsql'. Анхдагч 'mysql'.
     */
    public static function driver(): string
    {
        $driver = $_ENV['RAPTOR_DB_DRIVER'] ?? 'mysql';
        if (!\in_array($driver, ['mysql', 'pgsql'], true)) {
            throw new \Exception(
                "RAPTOR_DB_DRIVER зөвхөн 'mysql' эсвэл 'pgsql' байж болно. Авсан: '$driver'"
            );
        }
        return $driver;
    }

    /**
     * Тохиргооны дагуу өгөгдлийн санд холбогдож PDO instance буцаах.
     *
     * Database нь аль хэдийн үүссэн байх ёстой. Шинэ системд developer
     * өөрөө CREATE DATABASE ажиллуулна - implicit auto-create байхгүй.
     */
    public static function connect(): \PDO
    {
        return self::driver() === 'pgsql'
            ? self::connectPostgres()
            : self::connectMySQL();
    }

    private static function connectMySQL(): \PDO
    {
        $host      = $_ENV['RAPTOR_DB_HOST']      ?? 'localhost';
        $username  = $_ENV['RAPTOR_DB_USERNAME']  ?? 'root';
        $password  = $_ENV['RAPTOR_DB_PASSWORD']  ?? '';
        $charset   = $_ENV['RAPTOR_DB_CHARSET']   ?? 'utf8mb4';
        $collation = $_ENV['RAPTOR_DB_COLLATION'] ?? 'utf8mb4_unicode_ci';
        $database  = $_ENV['RAPTOR_DB_NAME']      ?? 'raptor';

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT         => $_ENV['RAPTOR_DB_PERSISTENT'] ?? false,
        ];

        $pdo = new \PDO(
            "mysql:host=$host;dbname=$database;charset=$charset",
            $username,
            $password,
            $options
        );
        $pdo->exec("SET NAMES '$charset' COLLATE '$collation'");

        return $pdo;
    }

    private static function connectPostgres(): \PDO
    {
        $host     = $_ENV['RAPTOR_DB_HOST']     ?? 'localhost';
        $username = $_ENV['RAPTOR_DB_USERNAME'] ?? 'postgres';
        $database = $_ENV['RAPTOR_DB_NAME']     ?? 'raptor';
        $password = $_ENV['RAPTOR_DB_PASSWORD']
            ?? throw new \Exception('RAPTOR_DB_PASSWORD is not set. Check your .env file!');

        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT         => $_ENV['RAPTOR_DB_PERSISTENT'] ?? false,
        ];

        return new \PDO(
            "pgsql:host=$host;dbname=$database;client_encoding=UTF8",
            $username,
            $password,
            $options
        );
    }
}
