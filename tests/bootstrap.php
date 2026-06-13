<?php

/**
 * PHPUnit test bootstrap
 *
 * Test орчныг бэлтгэх: autoload, .env, CODESAUR_DEVELOPMENT тогтмол
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Composer autoload
require_once __DIR__ . '/../vendor/autoload.php';

// .env.testing файл байвал түүнийг, байхгүй бол .env ачаалах
$root = dirname(__DIR__);
$envFile = file_exists("$root/.env.testing") ? '.env.testing' : '.env';

$dotenv = \Dotenv\Dotenv::createImmutable($root, $envFile);
$dotenv->load();

foreach ($_ENV as &$env) {
    if ($env === 'true')  { $env = true; }
    elseif ($env === 'false') { $env = false; }
}
unset($env);

// CODESAUR_DEVELOPMENT тогтмол
if (!defined('CODESAUR_DEVELOPMENT')) {
    define('CODESAUR_DEVELOPMENT', true);
}

// Цагийн бүс
if (!empty($_ENV['CODESAUR_APP_TIME_ZONE'])) {
    date_default_timezone_set($_ENV['CODESAUR_APP_TIME_ZONE']);
}

// Server params (Controller-д хэрэгтэй)
$_SERVER['SCRIPT_NAME']     = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] ?? "$root/public_html/index.php";

// Test database-г байхгүй бол үүсгэх (зөвхөн MySQL driver-т, developer-д амар).
// Production бол `\Raptor\DatabaseConnection::connect()` шууд connect хийдэг -
// энд тест орчинд CREATE DATABASE-ийг тусад нь ажиллуулна.
if ((($_ENV['RAPTOR_DB_DRIVER'] ?? 'mysql') === 'mysql')) {
    $host      = $_ENV['RAPTOR_DB_HOST']      ?? 'localhost';
    $username  = $_ENV['RAPTOR_DB_USERNAME']  ?? 'root';
    $password  = $_ENV['RAPTOR_DB_PASSWORD']  ?? '';
    $charset   = $_ENV['RAPTOR_DB_CHARSET']   ?? 'utf8mb4';
    $collation = $_ENV['RAPTOR_DB_COLLATION'] ?? 'utf8mb4_unicode_ci';
    $database  = $_ENV['RAPTOR_DB_NAME']      ?? 'raptor_test';

    try {
        $server = new \PDO(
            "mysql:host=$host;charset=$charset",
            $username,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $server->exec("CREATE DATABASE IF NOT EXISTS `$database` COLLATE $collation");
    } catch (\Throwable $e) {
        fwrite(STDERR, "Test DB бэлдэх алдаа: {$e->getMessage()}\n");
        exit(1);
    }
}
