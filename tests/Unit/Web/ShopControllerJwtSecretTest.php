<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use Web\Shop\ShopController;

/**
 * ShopController-ийн spam-token secret (SpamProtectionTrait::getSpamSecret())
 * - JWT secret fallback устгасан эсэхийг тестлэх.
 *
 * Env тохируулаагүй бол RuntimeException шидэх ёстой.
 */
class ShopControllerJwtSecretTest extends TestCase
{
    private \ReflectionMethod $getSpamSecret;
    private ShopController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $ref = new \ReflectionClass(ShopController::class);
        $this->controller = $ref->newInstanceWithoutConstructor();

        $this->getSpamSecret = new \ReflectionMethod(ShopController::class, 'getSpamSecret');
        $this->getSpamSecret->setAccessible(true);
    }

    /**
     * RAPTOR_JWT_SECRET тохируулсан үед зөв утга буцаах.
     */
    public function testReturnsSecretWhenEnvIsSet(): void
    {
        $original = $_ENV['RAPTOR_JWT_SECRET'] ?? null;
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret-key-12345';

        try {
            $result = $this->getSpamSecret->invoke($this->controller);
            $this->assertEquals('test-secret-key-12345', $result);
        } finally {
            // Restore
            if ($original === null) {
                unset($_ENV['RAPTOR_JWT_SECRET']);
            } else {
                $_ENV['RAPTOR_JWT_SECRET'] = $original;
            }
        }
    }

    /**
     * RAPTOR_JWT_SECRET тохируулаагүй үед RuntimeException шидэх.
     */
    public function testThrowsExceptionWhenEnvIsNotSet(): void
    {
        $original = $_ENV['RAPTOR_JWT_SECRET'] ?? null;
        unset($_ENV['RAPTOR_JWT_SECRET']);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('RAPTOR_JWT_SECRET environment variable is not set');
            $this->getSpamSecret->invoke($this->controller);
        } finally {
            if ($original !== null) {
                $_ENV['RAPTOR_JWT_SECRET'] = $original;
            }
        }
    }

    /**
     * RAPTOR_JWT_SECRET хоосон string үед RuntimeException шидэх.
     */
    public function testThrowsExceptionWhenEnvIsEmpty(): void
    {
        $original = $_ENV['RAPTOR_JWT_SECRET'] ?? null;
        $_ENV['RAPTOR_JWT_SECRET'] = '';

        try {
            $this->expectException(\RuntimeException::class);
            $this->getSpamSecret->invoke($this->controller);
        } finally {
            if ($original === null) {
                unset($_ENV['RAPTOR_JWT_SECRET']);
            } else {
                $_ENV['RAPTOR_JWT_SECRET'] = $original;
            }
        }
    }

    /**
     * Hardcoded fallback 'raptor-form-secret' ашиглагдахгүй болсон эсэх.
     */
    public function testNoHardcodedFallback(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/application/web/shop/ShopController.php'
        );

        $this->assertStringNotContainsString(
            "'raptor-form-secret'",
            $source,
            'Hardcoded fallback secret should be removed from ShopController'
        );
    }
}
