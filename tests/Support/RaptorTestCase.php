<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Raptor\Authentication\User;

/**
 * Unit test-ийн суурь класс.
 * DB холболт шаардахгүй, цэвэр логикийн тест.
 */
abstract class RaptorTestCase extends TestCase
{
    /**
     * Mock ServerRequest үүсгэх.
     */
    protected function createMockRequest(array $attributes = []): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getAttribute')
            ->willReturnCallback(function (string $name, $default = null) use ($attributes) {
                return $attributes[$name] ?? $default;
            });

        $request->method('getAttributes')
            ->willReturn($attributes);

        $serverParams = [
            'SCRIPT_NAME'     => '/index.php',
            'SCRIPT_FILENAME' => '/var/www/public_html/index.php',
            'REMOTE_ADDR'     => '127.0.0.1',
        ];
        $request->method('getServerParams')->willReturn($serverParams);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getRequestTarget')->willReturn('/');
        $request->method('getParsedBody')->willReturn([]);
        $request->method('getUploadedFiles')->willReturn([]);
        $request->method('getQueryParams')->willReturn([]);

        $uri = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/');
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    /**
     * Test User объект үүсгэх.
     */
    protected function createUser(array $rbac = [], array $profile = [], array $organization = []): User
    {
        $defaultProfile = [
            'id'         => 1,
            'username'   => 'testuser',
            'email'      => 'test@example.com',
            'first_name' => 'Test',
            'last_name'  => 'User',
            'phone'      => '+97699000000',
            'is_active'  => 1,
        ];

        $defaultOrg = [
            'id'    => 1,
            'name'  => 'Test Org',
            'alias' => 'system',
        ];

        return new User(
            array_merge($defaultProfile, $profile),
            array_merge($defaultOrg, $organization),
            $rbac
        );
    }

    /**
     * Coder (super admin) User.
     */
    protected function createCoder(): User
    {
        return $this->createUser(['system_coder' => []]);
    }

    /**
     * Admin User.
     */
    protected function createAdmin(): User
    {
        return $this->createUser([
            'system_admin' => [
                'system_content_index'  => true,
                'system_content_insert' => true,
                'system_content_update' => true,
                'system_content_delete' => true,
                'system_user_index'     => true,
                'system_user_insert'    => true,
                'system_user_update'    => true,
                'system_logger'         => true,
            ],
        ]);
    }

    /**
     * Эрхгүй User.
     */
    protected function createGuest(): User
    {
        return $this->createUser([]);
    }
}
