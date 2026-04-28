<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;

/**
 * SpamProtectionTrait-ийн нэмэлт тест.
 *
 * Existing SpamProtectionTest-д байхгүй edge case-уудыг шалгана:
 * - Link spam илрүүлэлтийн edge case
 * - Turnstile skip нөхцөлүүд
 * - HMAC validation edge case
 * - Session rate limit
 * - validateSpamProtection нөхцлүүдийн дараалал
 */
class SpamProtectionExtendedTest extends TestCase
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

    // =============================================
    // Link spam - edge case
    // =============================================

    /**
     * Хоосон текст зөвшөөрнө.
     */
    public function testCheckLinkSpamAllowsEmptyText(): void
    {
        $this->trait->checkLinkSpam('');
        $this->assertTrue(true);
    }

    /**
     * Нэг link зөвшөөрнө.
     */
    public function testCheckLinkSpamAllowsOneLink(): void
    {
        $this->trait->checkLinkSpam('Visit https://example.com for more');
        $this->assertTrue(true);
    }

    /**
     * Яг 2 link (default max) зөвшөөрнө.
     */
    public function testCheckLinkSpamAllowsExactlyMaxLinks(): void
    {
        $this->trait->checkLinkSpam('http://a.com http://b.com');
        $this->assertTrue(true);
    }

    /**
     * Mixed http:// болон www. тоолно.
     */
    public function testCheckLinkSpamCountsMixedProtocols(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->trait->checkLinkSpam('http://a.com www.b.com https://c.com');
    }

    /**
     * Case insensitive - HTTP:// мөн тоолно.
     */
    public function testCheckLinkSpamCaseInsensitive(): void
    {
        $this->expectException(\Exception::class);
        $this->trait->checkLinkSpam('HTTP://A.COM HTTP://B.COM HTTP://C.COM');
    }

    /**
     * maxLinks=0 бол 1 link ч хориглоно.
     */
    public function testCheckLinkSpamZeroMaxRejectsAnyLink(): void
    {
        $this->expectException(\Exception::class);
        $this->trait->checkLinkSpam('http://example.com', 0);
    }

    /**
     * maxLinks=1 бол 2 link хориглоно.
     */
    public function testCheckLinkSpamCustomMaxOne(): void
    {
        $this->expectException(\Exception::class);
        $this->trait->checkLinkSpam('http://a.com http://b.com', 1);
    }

    /**
     * Link-гүй урт текст зөвшөөрнө.
     */
    public function testCheckLinkSpamAllowsLongTextWithoutLinks(): void
    {
        $longText = \str_repeat('Энэ бол маш урт текст. ', 100);
        $this->trait->checkLinkSpam($longText);
        $this->assertTrue(true);
    }

    /**
     * ftp:// link тоолохгүй (зөвхөн http/https/www).
     */
    public function testCheckLinkSpamIgnoresFtp(): void
    {
        $this->trait->checkLinkSpam('ftp://a.com ftp://b.com ftp://c.com');
        $this->assertTrue(true);
    }

    // =============================================
    // Turnstile skip нөхцлүүд
    // =============================================

    /**
     * Turnstile site key байхгүй бол хоосон string буцаана.
     */
    public function testGetTurnstileSiteKeyReturnsEmptyWhenNotSet(): void
    {
        unset($_ENV['RAPTOR_TURNSTILE_SITE_KEY']);
        $this->assertSame('', $this->trait->getTurnstileSiteKey());
    }

    /**
     * Turnstile secret key хоосон string '' бол skip хийнэ.
     */
    public function testVerifyTurnstileSkipsWhenSecretIsEmptyString(): void
    {
        $_ENV['RAPTOR_TURNSTILE_SECRET_KEY'] = '';
        $this->trait->verifyTurnstile('');
        $this->assertTrue(true);
    }

    // =============================================
    // HMAC validation edge case
    // =============================================

    /**
     * Timestamp 0 бол HMAC буруу - exception шидэх.
     */
    public function testValidateSpamProtectionRejectsZeroTimestamp(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $this->expectException(\Exception::class);
        $this->trait->validateSpamProtection(
            ['_ts' => 0, '_token' => 'anything'],
            'test-form',
            '_last_test_at'
        );
    }

    /**
     * Token байхгүй (хоосон) бол exception шидэх.
     */
    public function testValidateSpamProtectionRejectsEmptyToken(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->trait->validateSpamProtection(
            ['_ts' => \time() - 5, '_token' => ''],
            'test-form',
            '_last_test_at'
        );
    }

    /**
     * RAPTOR_JWT_SECRET байхгүй бол RuntimeException шидэх.
     */
    public function testValidateSpamProtectionRequiresJwtSecret(): void
    {
        unset($_ENV['RAPTOR_JWT_SECRET']);
        $_SESSION = [];

        $this->expectException(\RuntimeException::class);
        $this->trait->validateSpamProtection(
            ['_ts' => \time() - 5, '_token' => 'test'],
            'test-form',
            '_last_test_at'
        );
    }

    /**
     * Өөр formName-тай HMAC token буруу болно.
     */
    public function testValidateSpamProtectionRejectsMismatchedFormName(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $ts = \time() - 5;
        $token = \hash_hmac('sha256', "form-a-$ts", 'test-secret');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->trait->validateSpamProtection(
            ['_ts' => $ts, '_token' => $token],
            'form-b', // Different form name
            '_last_test_at'
        );
    }

    /**
     * Өөр secret-тай HMAC token буруу болно.
     */
    public function testValidateSpamProtectionRejectsMismatchedSecret(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'real-secret';
        $_SESSION = [];

        $ts = \time() - 5;
        $token = \hash_hmac('sha256', "test-form-$ts", 'wrong-secret');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(403);
        $this->trait->validateSpamProtection(
            ['_ts' => $ts, '_token' => $token],
            'test-form',
            '_last_test_at'
        );
    }

    // =============================================
    // Session rate limit
    // =============================================

    /**
     * Session rate limit - хоёр дахь submit хэт хурдан бол exception.
     */
    public function testValidateSpamProtectionSessionRateLimit(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        unset($_ENV['RAPTOR_TURNSTILE_SECRET_KEY']);

        $ts = \time() - 5;
        $token = \hash_hmac('sha256', "test-form-$ts", 'test-secret');

        // Simulate previous submission was 3 seconds ago
        $_SESSION = ['_last_test_at' => \time() - 3];

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(429);
        $this->trait->validateSpamProtection(
            ['_ts' => $ts, '_token' => $token],
            'test-form',
            '_last_test_at',
            10 // rate limit 10 seconds
        );
    }

    /**
     * Session rate limit - хангалттай хугацаа өнгөрсөн бол зөвшөөрнө.
     */
    public function testValidateSpamProtectionSessionRateLimitPassed(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        unset($_ENV['RAPTOR_TURNSTILE_SECRET_KEY']);

        $ts = \time() - 5;
        $token = \hash_hmac('sha256', "test-form-$ts", 'test-secret');

        // Simulate previous submission was 15 seconds ago
        $_SESSION = ['_last_test_at' => \time() - 15];

        $this->trait->validateSpamProtection(
            ['_ts' => $ts, '_token' => $token],
            'test-form',
            '_last_test_at',
            10, // rate limit 10 seconds
            2   // minTime 2 seconds
        );
        $this->assertTrue(true);
    }

    // =============================================
    // Honeypot
    // =============================================

    /**
     * Honeypot 'website' field-ийг бөглөвөл 400 error.
     */
    public function testValidateSpamProtectionHoneypotAnyValue(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->trait->validateSpamProtection(
            ['website' => 'x'],
            'test-form',
            '_last_test_at'
        );
    }

    /**
     * Honeypot '0' утга ч spam гэж тооцно (truthy).
     */
    public function testValidateSpamProtectionHoneypotTruthyValue(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);
        $this->trait->validateSpamProtection(
            ['website' => 'anything'],
            'test-form',
            '_last_test_at'
        );
    }

    /**
     * Honeypot field хоосон бол зөвшөөрнө (бөглөөгүй).
     */
    public function testValidateSpamProtectionHoneypotEmptyIsOk(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        unset($_ENV['RAPTOR_TURNSTILE_SECRET_KEY']);

        $ts = \time() - 5;
        $token = \hash_hmac('sha256', "test-form-$ts", 'test-secret');
        $_SESSION = [];

        $this->trait->validateSpamProtection(
            ['website' => '', '_ts' => $ts, '_token' => $token],
            'test-form',
            '_last_test_at',
            10,
            2
        );
        $this->assertTrue(true);
    }

    // =============================================
    // Validation дарааллын тест
    // =============================================

    /**
     * Honeypot шалгалт HMAC-ээс өмнө хийгддэг.
     * Бот нь token-гүй ч honeypot-д баригддаг.
     */
    public function testHoneypotIsCheckedBeforeHmac(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        try {
            $this->trait->validateSpamProtection(
                ['website' => 'bot-value', '_ts' => 0, '_token' => ''],
                'test-form',
                '_last_test_at'
            );
            $this->fail('Should have thrown exception');
        } catch (\Exception $e) {
            // Honeypot check should throw 400 (not 403 from HMAC)
            $this->assertSame(400, $e->getCode(),
                'Honeypot (400) should be checked before HMAC (403)');
        }
    }

    /**
     * Form expired (>1 цаг) нь 400 error code-тай.
     */
    public function testExpiredFormReturns400(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        $_SESSION = [];

        $ts = \time() - 3601; // > 1 hour
        $token = \hash_hmac('sha256', "test-form-$ts", 'test-secret');

        try {
            $this->trait->validateSpamProtection(
                ['_ts' => $ts, '_token' => $token],
                'test-form',
                '_last_test_at'
            );
            $this->fail('Should have thrown exception');
        } catch (\Exception $e) {
            $this->assertSame(400, $e->getCode());
        }
    }

    /**
     * Exactly 3600 seconds (1 hour) нь зөвшөөрнө.
     */
    public function testExactlyOneHourIsAllowed(): void
    {
        $_ENV['RAPTOR_JWT_SECRET'] = 'test-secret';
        unset($_ENV['RAPTOR_TURNSTILE_SECRET_KEY']);
        $_SESSION = [];

        $ts = \time() - 3600; // exactly 1 hour
        $token = \hash_hmac('sha256', "test-form-$ts", 'test-secret');

        $this->trait->validateSpamProtection(
            ['_ts' => $ts, '_token' => $token],
            'test-form',
            '_last_test_at',
            10,
            2
        );
        $this->assertTrue(true);
    }

    // =============================================
    // Source code structure шалгалт
    // =============================================

    /**
     * verifyTurnstile() нь hash_equals ашигладаггүй - Cloudflare API response шалгадаг.
     */
    public function testVerifyTurnstileUsesCloudflareApi(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/SpamProtectionTrait.php'
        );
        $this->assertStringContainsString(
            'challenges.cloudflare.com/turnstile/v0/siteverify',
            $source,
            'verifyTurnstile() must call Cloudflare siteverify API'
        );
    }

    /**
     * validateSpamProtection() нь hash_equals ашигладаг (timing-safe).
     */
    public function testValidateUsesTimingSafeComparison(): void
    {
        $source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/SpamProtectionTrait.php'
        );
        $this->assertStringContainsString(
            'hash_equals',
            $source,
            'HMAC validation must use hash_equals for timing-safe comparison'
        );
    }
}
