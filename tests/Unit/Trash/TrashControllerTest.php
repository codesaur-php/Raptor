<?php

namespace Tests\Unit\Trash;

use PHPUnit\Framework\TestCase;

/**
 * TrashController-ийн source code шинжлэлийн тест.
 * Эрх шалгалт, route бүтэц шалгана.
 */
class TrashControllerTest extends TestCase
{
    private static string $controllerSource;
    private static string $routerSource;

    public static function setUpBeforeClass(): void
    {
        self::$controllerSource = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/trash/TrashController.php'
        );
        self::$routerSource = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/trash/TrashRouter.php'
        );
    }

    // --- Permission шалгалт ---

    public function testIndexRequiresSystemCoder(): void
    {
        $this->assertStringContainsString(
            "isUser('system_coder')",
            self::$controllerSource,
            'index() must check system_coder role'
        );
    }

    public function testListRequiresSystemCoder(): void
    {
        \preg_match('/function\s+list\s*\(\).*?\{(.+?)\n    \}/s', self::$controllerSource, $m);
        $this->assertStringContainsString("isUser('system_coder')", $m[1]);
    }

    public function testDeleteRequiresSystemCoder(): void
    {
        \preg_match('/function\s+delete\s*\(\).*?\{(.+?)\n    \}/s', self::$controllerSource, $m);
        $this->assertStringContainsString("isUser('system_coder')", $m[1]);
    }

    public function testEmptyRequiresSystemCoder(): void
    {
        \preg_match('/function\s+empty\s*\(\).*?\{(.+?)\n    \}/s', self::$controllerSource, $m);
        $this->assertStringContainsString("isUser('system_coder')", $m[1]);
    }

    // --- Route бүтэц ---

    public function testRouterHasIndexRoute(): void
    {
        $this->assertStringContainsString("'/dashboard/trash'", self::$routerSource);
        $this->assertStringContainsString("name('trash')", self::$routerSource);
    }

    public function testRouterHasListRoute(): void
    {
        $this->assertStringContainsString("'/dashboard/trash/list'", self::$routerSource);
        $this->assertStringContainsString("name('trash-list')", self::$routerSource);
    }

    public function testRouterHasViewRoute(): void
    {
        $this->assertStringContainsString("'/dashboard/trash/view/{uint:id}'", self::$routerSource);
        $this->assertStringContainsString("name('trash-view')", self::$routerSource);
    }

    public function testRouterHasDeleteRoute(): void
    {
        $this->assertStringContainsString("'/dashboard/trash/delete'", self::$routerSource);
        $this->assertStringContainsString("name('trash-delete')", self::$routerSource);
    }

    public function testRouterHasEmptyRoute(): void
    {
        $this->assertStringContainsString("'/dashboard/trash/empty'", self::$routerSource);
        $this->assertStringContainsString("name('trash-empty')", self::$routerSource);
    }

    // --- Delete logging ---

    public function testDeleteLogsCriticalLevel(): void
    {
        $this->assertStringContainsString(
            'LogLevel::CRITICAL',
            self::$controllerSource,
            'Permanent delete must log at CRITICAL level'
        );
    }

    public function testEmptyUsesDeleteSql(): void
    {
        \preg_match('/function\s+empty\s*\(\).*?\{(.+?)\n    \}/s', self::$controllerSource, $m);
        $this->assertStringContainsString('DELETE FROM', $m[1],
            'empty() must use DELETE SQL to clear all records');
    }
}
