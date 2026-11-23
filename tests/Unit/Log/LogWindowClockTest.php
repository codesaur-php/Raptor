<?php

namespace Tests\Unit\Log;

use Tests\Support\RaptorTestCase;

/**
 * Лог дээр тулгуурласан оролдлогын хязгаар (login, өөрийн нууц үг солих)
 * хугацааны хязгаарыг PHP-ийн цагаар тооцох ёстой: Logger created_at-ийг
 * PHP-ийн date()-ээр бичдэг, DB-ийн NOW() өөр цагийн бүстэй байж болно.
 */
class LogWindowClockTest extends RaptorTestCase
{
    public static function methods(): array
    {
        return [
            'login attempts' => [\Dashboard\Authentication\LoginController::class, 'checkLoginAttempts'],
            'own password attempts' => [\Dashboard\User\UsersController::class, 'assertPasswordAttemptsAllowed']
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('methods')]
    public function testWindowUsesPhpClockNotDatabaseNow(string $class, string $method): void
    {
        $ref = new \ReflectionMethod($class, $method);
        $lines = \file($ref->getFileName());
        $body = \implode('', \array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
        $this->assertDoesNotMatchRegularExpression('/NOW\(\)\s*-\s*INTERVAL|DATE_SUB\(\s*NOW\(\)/i', $body, "$method must not compare created_at with the DB clock");
        $this->assertStringContainsString("created_at > :since", $body);
        $this->assertStringContainsString("bindValue(':since', \\date(", $body);
    }
}
