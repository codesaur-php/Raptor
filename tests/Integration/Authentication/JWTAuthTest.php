<?php

namespace Tests\Integration\Authentication;

use Tests\Support\IntegrationTestCase;
use Raptor\Authentication\JWTAuthMiddleware;

class JWTAuthTest extends IntegrationTestCase
{
    private string $secret;
    private string $algorithm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret    = $_ENV['RAPTOR_JWT_SECRET'] ?? 'test-secret';
        $this->algorithm = $_ENV['RAPTOR_JWT_ALGORITHM'] ?? 'HS256';
    }

    public function testGenerateToken(): void
    {
        $payload = [
            'user_id' => 1,
            'org_id'  => 1,
            'iat'     => time(),
            'exp'     => time() + 3600,
        ];

        $jwt = \Firebase\JWT\JWT::encode($payload, $this->secret, $this->algorithm);

        $this->assertIsString($jwt);
        $this->assertNotEmpty($jwt);
        // JWT format: header.payload.signature
        $this->assertCount(3, explode('.', $jwt));
    }

    public function testDecodeValidToken(): void
    {
        $payload = [
            'user_id' => 42,
            'org_id'  => 5,
            'iat'     => time(),
            'exp'     => time() + 3600,
        ];

        $jwt = \Firebase\JWT\JWT::encode($payload, $this->secret, $this->algorithm);
        $decoded = \Firebase\JWT\JWT::decode(
            $jwt,
            new \Firebase\JWT\Key($this->secret, $this->algorithm)
        );

        $this->assertEquals(42, $decoded->user_id);
        $this->assertEquals(5, $decoded->org_id);
    }

    public function testExpiredTokenThrows(): void
    {
        $payload = [
            'user_id' => 1,
            'org_id'  => 1,
            'iat'     => time() - 7200,
            'exp'     => time() - 3600, // expired 1 hour ago
        ];

        $jwt = \Firebase\JWT\JWT::encode($payload, $this->secret, $this->algorithm);

        $this->expectException(\Firebase\JWT\ExpiredException::class);

        \Firebase\JWT\JWT::decode(
            $jwt,
            new \Firebase\JWT\Key($this->secret, $this->algorithm)
        );
    }

    public function testInvalidSignatureThrows(): void
    {
        $payload = [
            'user_id' => 1,
            'org_id'  => 1,
            'iat'     => time(),
            'exp'     => time() + 3600,
        ];

        $jwt = \Firebase\JWT\JWT::encode($payload, $this->secret, $this->algorithm);

        $this->expectException(\Throwable::class);

        \Firebase\JWT\JWT::decode(
            $jwt,
            new \Firebase\JWT\Key('wrong-secret', $this->algorithm)
        );
    }
}
