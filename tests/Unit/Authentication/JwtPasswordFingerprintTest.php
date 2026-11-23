<?php

namespace Tests\Unit\Authentication;

use Tests\Support\RaptorTestCase;

use Dashboard\Authentication\JWTAuthMiddleware;

/**
 * Нууц үг солигдоход хэрэглэгчийн бусад session дуусна: session-ий JWT бүр
 * нууц үгийн hash-ийн HMAC тамгатай (JWTAuthMiddleware::issue()), process() нь
 * тамга одоогийн hash-тай таарахгүй токеныг хүлээж авахгүй.
 */
class JwtPasswordFingerprintTest extends RaptorTestCase
{
    private ?string $originalSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalSecret = $_ENV['RAPTOR_JWT_SECRET'] ?? null;
        $_ENV['RAPTOR_JWT_SECRET'] = \str_repeat('a1', 32);
    }

    protected function tearDown(): void
    {
        if ($this->originalSecret === null) {
            unset($_ENV['RAPTOR_JWT_SECRET']);
        } else {
            $_ENV['RAPTOR_JWT_SECRET'] = $this->originalSecret;
        }
        parent::tearDown();
    }

    public function testIssuedTokenCarriesFingerprintOfPasswordHash(): void
    {
        $jwt = new JWTAuthMiddleware();
        $hash = \password_hash('secret', \PASSWORD_BCRYPT);
        $claims = $jwt->validate($jwt->issue(['id' => 7, 'password' => $hash], 3));

        $this->assertSame(7, $claims['user_id']);
        $this->assertSame(3, $claims['organization_id']);
        $this->assertSame($jwt->passwordFingerprint($hash), $claims[JWTAuthMiddleware::PASSWORD_CLAIM]);
        /* Hash өөрөө токенд орохгүй */
        $this->assertNotContains($hash, $claims);
    }

    public function testFingerprintChangesWithPassword(): void
    {
        $jwt = new JWTAuthMiddleware();
        $old = \password_hash('secret', \PASSWORD_BCRYPT);
        $new = \password_hash('secret', \PASSWORD_BCRYPT);
        $this->assertSame($jwt->passwordFingerprint($old), $jwt->passwordFingerprint($old));
        $this->assertNotSame($jwt->passwordFingerprint($old), $jwt->passwordFingerprint($new));
        $this->assertSame(32, \strlen($jwt->passwordFingerprint($old)));
    }

    public function testMiddlewareRejectsTokenWithStaleFingerprint(): void
    {
        $source = \file_get_contents(\dirname(__DIR__, 3) . '/application/dashboard/authentication/JWTAuthMiddleware.php');
        $this->assertMatchesRegularExpression(
            '/hash_equals\(\s*\$this->passwordFingerprint\(\(string\)\(\$profile\[\'password\'\]/',
            $source,
            'process() must compare the token fingerprint with the stored password hash'
        );
    }

    public function testSessionTokensAreIssuedOnlyThroughIssue(): void
    {
        foreach (['authentication/LoginController.php', 'user/UsersController.php'] as $file) {
            $source = \file_get_contents(\dirname(__DIR__, 3) . "/application/dashboard/$file");
            $this->assertStringNotContainsString('JWTAuthMiddleware())->generate(', $source, $file);
        }
        $login = \file_get_contents(\dirname(__DIR__, 3) . '/application/dashboard/authentication/LoginController.php');
        $this->assertSame(2, \substr_count($login, 'JWTAuthMiddleware())->issue('));
    }

    public function testPasswordChangeKeepsSessionWritable(): void
    {
        $source = \file_get_contents(\dirname(__DIR__, 3) . '/application/dashboard/Application.php');
        $this->assertStringContainsString("'/users/set/password/'", $source);
    }

    public function testSessionLockIsReleasedBeforeEmail(): void
    {
        /* SMTP илгээлтийн турш session түгжигдэх ёсгүй */
        $ref = new \ReflectionMethod(\Dashboard\User\UsersController::class, 'setPassword');
        $lines = \file($ref->getFileName());
        $body = \implode('', \array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
        $issue = \strpos($body, 'JWTAuthMiddleware())->issue(');
        $close = \strpos($body, 'session_write_close()');
        $mail = \strpos($body, '$this->notifyPasswordChanged(');
        $this->assertNotFalse($issue);
        $this->assertNotFalse($close);
        $this->assertNotFalse($mail);
        $this->assertTrue($issue < $close && $close < $mail, 'session_write_close() must run after the JWT is written and before the email is sent');
    }
}
