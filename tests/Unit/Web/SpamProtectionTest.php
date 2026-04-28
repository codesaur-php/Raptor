<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;

/**
 * SpamProtectionTrait-ийн unit тест.
 */
class SpamProtectionTest extends TestCase
{
    private object $trait;
    private array $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = [
            'RAPTOR_JWT_SECRET'         => $_ENV['RAPTOR_JWT_SECRET'] ?? null,
            'RAPTOR_TURNSTILE_SITE_KEY' => $_ENV['RAPTOR_TURNSTILE_SITE_KEY'] ?? null,
            'RAPTOR_TURNSTILE_SECRET_KEY' => $_ENV['RAPTOR_TURNSTILE_SECRET_KEY'] ?? null,
        ];

        $this->trait = new class {
            use \Raptor\SpamProtectionTrait {
                getTurnstileSiteKey as public;
                checkLinkSpam as public;
                validateSpamProtection as public;
                verifyTurnstile as public;
            }
        };
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Turnstile site key хоосон бол хоосон string буцаах.
     */
    public function testGetTurnstileSiteKeyEmpty(): void
    {
        unset($_ENV['RAPTOR_TURNSTILE_SITE_KEY']);
        $result = $this->trait->getTurnstileSiteKey();
        $this->assertSame('', $result);
    }

    /**
     * Turnstile site key .env-д байвал буцаах.
     */
    public function testGetTurnstileSiteKeySet(): void
    {
        $_ENV['RAPTOR_TURNSTILE_SITE_KEY'] = 'test-site-key';
        $result = $this->trait->getTurnstileSiteKey();
        $this->assertSame('test-site-key', $result);
        unset($_ENV['RAPTOR_TURNSTILE_SITE_KEY']);
    }

    /**
     * Turnstile secret key байхгүй бол verify skip хийх.
     */
    public function testVerifyTurnstileSkipsWhenNoSecret(): void
    {
        unset($_ENV['RAPTOR_TURNSTILE_SECRET_KEY']);
        // Exception шидэхгүй байх ёстой
        $this->trait->verifyTurnstile('');
        $this->assertTrue(true);
    }

    /**
     * Turnstile secret key байгаа ч token хоосон бол exception шидэх.
     */
    public function testVerifyTurnstileThrowsWhenTokenEmpty(): void
    {
        $_ENV['RAPTOR_TURNSTILE_SECRET_KEY'] = 'test-secret';
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->trait->verifyTurnstile('');
        unset($_ENV['RAPTOR_TURNSTILE_SECRET_KEY']);
    }

    /**
     * Link spam шүүлтүүр - 2 link зөвшөөрнө.
     */
    public function testCheckLinkSpamAllowsTwo(): void
    {
        $this->trait->checkLinkSpam('Check http://example.com and https://test.com');
        $this->assertTrue(true);
    }

    /**
     * Link spam шүүлтүүр - 3+ link хаана.
     */
    public function testCheckLinkSpamBlocksThree(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->trait->checkLinkSpam('Visit http://a.com http://b.com http://c.com');
    }

    /**
     * Link spam шүүлтүүр - www. мөн тоолно.
     */
    public function testCheckLinkSpamCountsWww(): void
    {
        $this->expectException(\Exception::class);
        $this->trait->checkLinkSpam('Go to www.a.com www.b.com www.c.com');
    }

    /**
     * Link spam шүүлтүүр - link байхгүй текст зөвшөөрнө.
     */
    public function testCheckLinkSpamAllowsPlainText(): void
    {
        $this->trait->checkLinkSpam('Сайн байна уу! Би танд мессеж бичиж байна.');
        $this->assertTrue(true);
    }

    /**
     * Link spam шүүлтүүр - maxLinks параметр тохируулж болно.
     */
    public function testCheckLinkSpamCustomMax(): void
    {
        $this->trait->checkLinkSpam('http://a.com http://b.com http://c.com', 5);
        $this->assertTrue(true);
    }

    /**
     * Spam validation - honeypot бөглөсөн бол exception.
     */
    public function testValidateSpamProtectionHoneypot(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->trait->validateSpamProtection(
            ['website' => 'spam-bot-filled-this'],
            'test-form',
            '_last_test_at'
        );
        unset($_ENV['RAPTOR_JWT_SECRET']);
    }

    /**
     * Spam validation - буруу HMAC token бол exception.
     */
    public function testValidateSpamProtectionInvalidToken(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->trait->validateSpamProtection(
            ['_ts' => \time() - 5, '_token' => 'wrong-token'],
            'test-form',
            '_last_test_at'
        );
        unset($_ENV['RAPTOR_JWT_SECRET']);
    }

    /**
     * Spam validation - хэт хурдан submit (minTime).
     */
    public function testValidateSpamProtectionTooFast(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $ts = \time(); // just now
        $token = \hash_hmac('sha256', "test-form-$ts", 'test-secret');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(429);
        $this->trait->validateSpamProtection(
            ['_ts' => $ts, '_token' => $token],
            'test-form',
            '_last_test_at',
            10,
            3 // 3 секундээс хурдан
        );
        unset($_ENV['RAPTOR_JWT_SECRET']);
    }

    /**
     * Spam validation - зөв token, хугацаа хангалттай бол амжилттай.
     */
    public function testValidateSpamProtectionSuccess(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        unset($_ENV['RAPTOR_TURNSTILE_SECRET_KEY']);
        $_SESSION = [];

        $ts = \time() - 5; // 5 секундын өмнө
        $token = \hash_hmac('sha256', "test-form-$ts", 'test-secret');

        $this->trait->validateSpamProtection(
            ['_ts' => $ts, '_token' => $token],
            'test-form',
            '_last_test_at',
            10,
            2
        );
        $this->assertTrue(true);

        unset($_ENV['RAPTOR_JWT_SECRET']);
    }

    /**
     * Spam validation - form хугацаа дууссан (1 цагаас хэтэрсэн).
     */
    public function testValidateSpamProtectionExpired(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $ts = \time() - 7200; // 2 цагийн өмнө
        $token = \hash_hmac('sha256', "test-form-$ts", 'test-secret');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->trait->validateSpamProtection(
            ['_ts' => $ts, '_token' => $token],
            'test-form',
            '_last_test_at'
        );
        unset($_ENV['RAPTOR_JWT_SECRET']);
    }
}
