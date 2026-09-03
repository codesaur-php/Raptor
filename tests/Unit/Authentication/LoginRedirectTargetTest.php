<?php

namespace Tests\Unit\Authentication;

use Tests\Support\RaptorTestCase;

use Dashboard\Authentication\LoginController;

/**
 * LoginController::sanitizeRedirectTarget() - нэвтэрсний дараа очих замын
 * open redirect шүүлтүүрийн тестүүд (DB шаардахгүй pure логик).
 *
 * JWTAuthMiddleware нэвтрээгүй хэрэглэгчийг /dashboard/login?redirect=...
 * руу илгээдэг бол энэ функц тэр параметрийг нэвтэрсний дараа ашиглахаас
 * өмнө шүүнэ: зөвхөн dashboard mount доорх same-origin зам л зөвшөөрөгдөнө.
 */
class LoginRedirectTargetTest extends RaptorTestCase
{
    private const BASE = '/dashboard';

    // =============================================
    // Зөвшөөрөгдөх замууд
    // =============================================

    public function testDashboardModulePathIsAccepted(): void
    {
        $this->assertSame(
            '/dashboard/news',
            LoginController::sanitizeRedirectTarget('/dashboard/news', self::BASE)
        );
    }

    public function testQueryStringIsPreserved(): void
    {
        $this->assertSame(
            '/dashboard/news/view/12?tab=comments',
            LoginController::sanitizeRedirectTarget('/dashboard/news/view/12?tab=comments', self::BASE)
        );
    }

    public function testBareMountPathIsAccepted(): void
    {
        $this->assertSame('/dashboard', LoginController::sanitizeRedirectTarget('/dashboard', self::BASE));
        $this->assertSame('/dashboard?x=1', LoginController::sanitizeRedirectTarget('/dashboard?x=1', self::BASE));
    }

    public function testSubfolderInstallBaseIsHonored(): void
    {
        $this->assertSame(
            '/site/dashboard/pages',
            LoginController::sanitizeRedirectTarget('/site/dashboard/pages', '/site/dashboard')
        );
        // Өөр subfolder-ийн dashboard руу гарахыг зөвшөөрөхгүй
        $this->assertNull(LoginController::sanitizeRedirectTarget('/dashboard/pages', '/site/dashboard'));
    }

    // =============================================
    // Татгалзах замууд
    // =============================================

    public function testEmptyAndNonStringAreRejected(): void
    {
        $this->assertNull(LoginController::sanitizeRedirectTarget(null, self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget('', self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget(['/dashboard/news'], self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget(42, self::BASE));
    }

    public function testAbsoluteUrlIsRejected(): void
    {
        $this->assertNull(LoginController::sanitizeRedirectTarget('https://evil.example/dashboard', self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget('javascript:alert(1)', self::BASE));
    }

    public function testProtocolRelativeUrlIsRejected(): void
    {
        $this->assertNull(LoginController::sanitizeRedirectTarget('//evil.example/dashboard', self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget('/\\evil.example/dashboard', self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget('/dashboard\\@evil.example', self::BASE));
    }

    public function testHeaderInjectionCharactersAreRejected(): void
    {
        $this->assertNull(LoginController::sanitizeRedirectTarget("/dashboard/news\r\nSet-Cookie: a=b", self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget("/dashboard/news\0", self::BASE));
    }

    public function testPathOutsideDashboardMountIsRejected(): void
    {
        $this->assertNull(LoginController::sanitizeRedirectTarget('/', self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget('/news', self::BASE));
        // Prefix таарсан ч өөр зам: /dashboardx
        $this->assertNull(LoginController::sanitizeRedirectTarget('/dashboardx/news', self::BASE));
    }

    public function testLoginPagesAreRejectedToAvoidLoop(): void
    {
        $this->assertNull(LoginController::sanitizeRedirectTarget('/dashboard/login', self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget('/dashboard/login/logout', self::BASE));
        $this->assertNull(LoginController::sanitizeRedirectTarget('/dashboard/login?redirect=/dashboard/news', self::BASE));
    }
}
