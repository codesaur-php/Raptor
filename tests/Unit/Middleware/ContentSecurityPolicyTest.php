<?php

namespace Tests\Unit\Middleware;

use Tests\Support\RaptorTestCase;

use Dashboard\ContentSecurityPolicy;

/**
 * ContentSecurityPolicy - хамгийн бага CSP header.
 *
 * - Web ба Dashboard тус бүр зөв бодлого авна (dashboard-д л frame-ancestors).
 * - script-src / default-src гэх мэт inline script, CDN, iframe эвдэх directive байхгүй.
 * - Аль хэдийн илгээгдсэн CSP-г дарж бичихгүй.
 * - Header зөвхөн HTML render замд илгээгдэнэ (index.php, app-wide middleware,
 *   файл дамжуулагч controller-т биш - inline PDF хоосон харагдана).
 */
class ContentSecurityPolicyTest extends RaptorTestCase
{
    private static function root(): string
    {
        return \dirname(__DIR__, 3);
    }

    /**
     * Method-ийн body-г source-оос таслан авна.
     */
    private function methodSource(string $class, string $method): string
    {
        $ref = new \ReflectionMethod($class, $method);
        $lines = \file($ref->getFileName());
        return \implode('', \array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
    }

    public function testWebPolicy(): void
    {
        $this->assertSame("object-src 'none'; base-uri 'self'", ContentSecurityPolicy::WEB);
        $this->assertStringNotContainsString('frame-ancestors', ContentSecurityPolicy::WEB);
    }

    public function testDashboardPolicy(): void
    {
        $this->assertSame(
            "object-src 'none'; base-uri 'self'; frame-ancestors 'self'",
            ContentSecurityPolicy::DASHBOARD
        );
    }

    public function testNoDirectiveThatBreaksScriptsCdnOrIframes(): void
    {
        foreach ([ContentSecurityPolicy::WEB, ContentSecurityPolicy::DASHBOARD] as $policy) {
            foreach (['default-src', 'script-src', 'style-src', 'img-src', 'frame-src', 'connect-src'] as $directive) {
                $this->assertStringNotContainsString($directive, $policy);
            }
        }
    }

    public function testHeaderLine(): void
    {
        $this->assertSame(
            "Content-Security-Policy: object-src 'none'; base-uri 'self'",
            ContentSecurityPolicy::headerLine(ContentSecurityPolicy::WEB, ['Content-Type: text/html'])
        );
    }

    public function testExistingPolicyIsNotOverwritten(): void
    {
        $this->assertNull(ContentSecurityPolicy::headerLine(
            ContentSecurityPolicy::DASHBOARD,
            ["content-security-policy: default-src 'self'"]
        ));
    }

    public function testDashboardRenderPathsSendDashboardPolicy(): void
    {
        $paths = [
            $this->methodSource(\Dashboard\Template\DashboardTrait::class, 'dashboardTemplate'),
            $this->methodSource(\Dashboard\Exception\ErrorHandler::class, 'exception'),
        ];
        foreach ($paths as $source) {
            $this->assertMatchesRegularExpression('/ContentSecurityPolicy::send\(\s*\\\\?(Dashboard\\\\)?ContentSecurityPolicy::DASHBOARD\s*\)/', $source);
            $this->assertStringNotContainsString('ContentSecurityPolicy::WEB', $source);
        }

        // Login / нууц үг сэргээх хуудас (standalone template) мөн dashboard-ын бодлоготой
        $login = \file_get_contents(self::root() . '/application/dashboard/authentication/LoginController.php');
        $this->assertSame(3, \substr_count($login, 'ContentSecurityPolicy::send(ContentSecurityPolicy::DASHBOARD)'));
        $this->assertStringNotContainsString('ContentSecurityPolicy::WEB', $login);
    }

    public function testWebRenderPathsSendWebPolicy(): void
    {
        $paths = [
            $this->methodSource(\Web\Template\TemplateController::class, 'webTemplate'),
            $this->methodSource(\Web\Template\ExceptionHandler::class, 'exception'),
        ];
        foreach ($paths as $source) {
            $this->assertStringContainsString('ContentSecurityPolicy::send(ContentSecurityPolicy::WEB)', $source);
            $this->assertStringNotContainsString('ContentSecurityPolicy::DASHBOARD', $source);
        }
    }

    public function testNotSentFromEntryPointMiddlewareOrFileResponses(): void
    {
        // Critical: бүх хариунд хүрдэг газар CSP илгээвэл inline PDF хоосон харагдана
        $files = [
            '/public_html/index.php',
            '/application/dashboard/Application.php',
            '/application/web/Application.php',
            '/application/dashboard/Controller.php',
            '/application/dashboard/file/ProtectedFilesController.php',
        ];
        foreach ($files as $file) {
            $this->assertStringNotContainsString(
                'ContentSecurityPolicy',
                \file_get_contents(self::root() . $file),
                "$file must not send the CSP header"
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'Content-Security-Policy',
                \file_get_contents(self::root() . $file),
                "$file must not send the CSP header"
            );
        }
        foreach (\glob(self::root() . '/application/dashboard/*Middleware.php') as $file) {
            $this->assertStringNotContainsString('ContentSecurityPolicy', \file_get_contents($file), $file);
        }
    }
}
