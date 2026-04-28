<?php

namespace Tests\Support;

/**
 * Integration test-ийн суурь класс.
 * MySQL test DB-тэй холбогдож, transaction isolation ашиглана.
 */
abstract class IntegrationTestCase extends RaptorTestCase
{
    protected static ?\PDO $pdo = null;

    /**
     * Test class эхлэхэд DB холболт үүсгэх.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (static::$pdo !== null) {
            return;
        }

        $host     = $_ENV['RAPTOR_DB_HOST'] ?? 'localhost';
        $dbname   = $_ENV['RAPTOR_DB_NAME'] ?? 'raptor_test';
        $username = $_ENV['RAPTOR_DB_USERNAME'] ?? 'root';
        $password = $_ENV['RAPTOR_DB_PASSWORD'] ?? '';
        $charset  = $_ENV['RAPTOR_DB_CHARSET'] ?? 'utf8mb4';
        $collation = $_ENV['RAPTOR_DB_COLLATION'] ?? 'utf8mb4_unicode_ci';

        // DB байхгүй бол үүсгэх
        $tmpPdo = new \PDO(
            "mysql:host=$host",
            $username,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $tmpPdo->exec(
            "CREATE DATABASE IF NOT EXISTS `$dbname` " .
            "CHARACTER SET $charset COLLATE $collation"
        );
        unset($tmpPdo);

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        static::$pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        static::$pdo->exec("SET NAMES $charset COLLATE $collation");
    }

    /**
     * Тест бүрийн өмнө transaction эхлүүлэх.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (static::$pdo !== null && !static::$pdo->inTransaction()) {
            static::$pdo->beginTransaction();
        }
    }

    /**
     * Тест бүрийн дараа rollback хийж DB-г цэвэр байлгах.
     */
    protected function tearDown(): void
    {
        if (static::$pdo !== null && static::$pdo->inTransaction()) {
            static::$pdo->rollBack();
        }

        parent::tearDown();
    }

    /**
     * PDO instance авах.
     */
    protected function getPdo(): \PDO
    {
        return static::$pdo;
    }

    /**
     * DB холболттой mock request үүсгэх.
     */
    protected function createAuthenticatedRequest(
        array $rbac = [],
        array $localization = []
    ): \Psr\Http\Message\ServerRequestInterface {
        $defaultLocalization = [
            'code'     => 'mn',
            'language' => [
                ['code' => 'mn', 'title' => 'Монгол'],
                ['code' => 'en', 'title' => 'English'],
            ],
            'text' => [],
        ];

        return $this->createMockRequest([
            'pdo'          => static::$pdo,
            'user'         => $this->createUser($rbac),
            'localization' => array_merge($defaultLocalization, $localization),
        ]);
    }
}
