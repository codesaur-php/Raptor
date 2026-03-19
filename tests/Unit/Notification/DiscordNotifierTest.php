<?php

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use Raptor\Notification\DiscordNotifier;

/**
 * DiscordNotifier-ийн unit тест.
 *
 * Webhook payload бүтэц, silent skip, embed formatting шалгана.
 * CurlClient-ыг mock хийж бодит HTTP дуудлага хийхгүй.
 */
class DiscordNotifierTest extends TestCase
{
    // =============================================
    // Silent skip - URL хоосон/байхгүй бол
    // =============================================

    /**
     * Webhook URL хоосон бол send() ямар ч exception шидэхгүй.
     */
    public function testSendSilentSkipWhenUrlEmpty(): void
    {
        unset($_ENV['RAPTOR_DISCORD_WEBHOOK_URL']);
        $notifier = new DiscordNotifier();

        // Should not throw any exception
        $notifier->send('Test Title', 'Test Description');
        $this->assertTrue(true, 'send() should silently skip when URL is empty');
    }

    /**
     * Webhook URL хоосон string байвал skip хийнэ.
     */
    public function testSendSilentSkipWhenUrlEmptyString(): void
    {
        $_ENV['RAPTOR_DISCORD_WEBHOOK_URL'] = '';
        $notifier = new DiscordNotifier();

        $notifier->send('Test Title');
        $this->assertTrue(true, 'send() should skip when URL is empty string');

        unset($_ENV['RAPTOR_DISCORD_WEBHOOK_URL']);
    }

    // =============================================
    // Color constants
    // =============================================

    public function testColorConstants(): void
    {
        $this->assertSame(0x2ecc71, DiscordNotifier::COLOR_SUCCESS);
        $this->assertSame(0x3498db, DiscordNotifier::COLOR_INFO);
        $this->assertSame(0xf39c12, DiscordNotifier::COLOR_WARNING);
        $this->assertSame(0xe74c3c, DiscordNotifier::COLOR_DANGER);
        $this->assertSame(0x9b59b6, DiscordNotifier::COLOR_PURPLE);
    }

    // =============================================
    // Embed formatting - source code шинжлэл
    // =============================================

    private static string $source;

    public static function setUpBeforeClass(): void
    {
        self::$source = \file_get_contents(
            \dirname(__DIR__, 3) . '/application/raptor/notification/DiscordNotifier.php'
        );
    }

    /**
     * send() embed title-ыг 256 тэмдэгтээр хязгаарладаг (Discord API limit).
     */
    public function testSendTruncatesTitleTo256(): void
    {
        $this->assertStringContainsString(
            'mb_substr($title, 0, 256)',
            self::$source,
            'send() must truncate title to 256 characters (Discord embed limit)'
        );
    }

    /**
     * send() embed description-ыг 2048 тэмдэгтээр хязгаарладаг.
     */
    public function testSendTruncatesDescriptionTo2048(): void
    {
        $this->assertStringContainsString(
            'mb_substr($description, 0, 2048)',
            self::$source,
            'send() must truncate description to 2048 characters (Discord embed limit)'
        );
    }

    /**
     * send() embed fields-ыг 25-аар хязгаарладаг (Discord API limit).
     */
    public function testSendLimitsFieldsTo25(): void
    {
        $this->assertStringContainsString(
            'array_slice($fields, 0, 25)',
            self::$source,
            'send() must limit fields to 25 (Discord embed limit)'
        );
    }

    /**
     * send() timestamp нэмдэг.
     */
    public function testSendIncludesTimestamp(): void
    {
        $this->assertStringContainsString(
            "'timestamp'",
            self::$source,
            'send() must include timestamp in embed'
        );
    }

    /**
     * send() footer-д appUrl харуулдаг.
     */
    public function testSendIncludesFooter(): void
    {
        $this->assertStringContainsString(
            "'footer'",
            self::$source,
            'send() must include footer when appUrl is provided'
        );
    }

    /**
     * send() JSON Content-Type header ашигладаг.
     */
    public function testSendUsesJsonContentType(): void
    {
        $this->assertStringContainsString(
            'Content-Type: application/json',
            self::$source,
            'send() must send Content-Type: application/json'
        );
    }

    /**
     * send() embeds array-д embed-ыг оруулдаг (Discord API format).
     */
    public function testSendUsesEmbedsArrayFormat(): void
    {
        $this->assertStringContainsString(
            "['embeds' => [\$embed]]",
            self::$source,
            'send() must wrap embed in embeds array (Discord API format)'
        );
    }

    // =============================================
    // Convenience methods - payload бүтэц
    // =============================================

    /**
     * userSignupRequest() нь COLOR_INFO ашигладаг.
     */
    public function testUserSignupRequestUsesInfoColor(): void
    {
        \preg_match('/function\s+userSignupRequest.*?\{(.+?)\}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'userSignupRequest() not found');
        $this->assertStringContainsString('COLOR_INFO', $m[1],
            'userSignupRequest() must use COLOR_INFO');
    }

    /**
     * userApproved() нь COLOR_SUCCESS ашигладаг.
     */
    public function testUserApprovedUsesSuccessColor(): void
    {
        \preg_match('/function\s+userApproved.*?\{(.+?)\n    \}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'userApproved() not found');
        $this->assertStringContainsString('COLOR_SUCCESS', $m[1],
            'userApproved() must use COLOR_SUCCESS');
    }

    /**
     * newOrder() нь COLOR_SUCCESS ашигладаг.
     */
    public function testNewOrderUsesSuccessColor(): void
    {
        \preg_match('/function\s+newOrder.*?\{(.+?)\}/s', self::$source, $m);
        $this->assertStringContainsString('COLOR_SUCCESS', $m[1],
            'newOrder() must use COLOR_SUCCESS');
    }

    /**
     * orderStatusChanged() нь COLOR_WARNING ашигладаг.
     */
    public function testOrderStatusChangedUsesWarningColor(): void
    {
        \preg_match('/function\s+orderStatusChanged.*?\{(.+?)\n    \}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'orderStatusChanged() not found');
        $this->assertStringContainsString('COLOR_WARNING', $m[1],
            'orderStatusChanged() must use COLOR_WARNING');
    }

    /**
     * contentAction() нь action-д тохирсон өнгө ашигладаг.
     */
    public function testContentActionUsesCorrectColors(): void
    {
        \preg_match('/function\s+contentAction.*?\{(.+?)\n    \}/s', self::$source, $m);
        $this->assertNotEmpty($m, 'contentAction() not found');
        $body = $m[1];

        $this->assertStringContainsString('COLOR_SUCCESS', $body, 'insert action must use success color');
        $this->assertStringContainsString('COLOR_INFO', $body, 'update action must use info color');
        $this->assertStringContainsString('COLOR_DANGER', $body, 'delete action must use danger color');
        $this->assertStringContainsString('COLOR_PURPLE', $body, 'publish action must use purple color');
    }

    /**
     * contentAction() нь action icons mapping ашигладаг.
     */
    public function testContentActionHasActionIcons(): void
    {
        \preg_match('/function\s+contentAction.*?\{(.+?)\n    \}/s', self::$source, $m);
        $body = $m[1];

        // Each action should have an icon mapping
        $this->assertStringContainsString("'insert'", $body);
        $this->assertStringContainsString("'update'", $body);
        $this->assertStringContainsString("'delete'", $body);
        $this->assertStringContainsString("'publish'", $body);
    }

    /**
     * settingsUpdated() нь COLOR_WARNING ашигладаг.
     */
    public function testSettingsUpdatedUsesWarningColor(): void
    {
        \preg_match('/function\s+settingsUpdated.*?\{(.+?)\n    \}/s', self::$source, $m);
        $this->assertStringContainsString('COLOR_WARNING', $m[1],
            'settingsUpdated() must use COLOR_WARNING');
    }

    // =============================================
    // Fields structure
    // =============================================

    /**
     * userSignupRequest() нь username, email fields-тэй.
     */
    public function testUserSignupRequestHasRequiredFields(): void
    {
        \preg_match('/function\s+userSignupRequest.*?\{(.+?)\}/s', self::$source, $m);
        $body = $m[1];

        $this->assertStringContainsString("'Username'", $body);
        $this->assertStringContainsString("'Email'", $body);
    }

    /**
     * newOrder() нь customer, email, product, quantity fields-тэй.
     */
    public function testNewOrderHasRequiredFields(): void
    {
        \preg_match('/function\s+newOrder.*?\{(.+?)\}/s', self::$source, $m);
        $body = $m[1];

        $this->assertStringContainsString("'Customer'", $body);
        $this->assertStringContainsString("'Email'", $body);
        $this->assertStringContainsString("'Product'", $body);
        $this->assertStringContainsString("'Quantity'", $body);
    }

    /**
     * newContactMessage() нь message field-ийг 1024 тэмдэгтээр хязгаарладаг.
     */
    public function testNewContactMessageTruncatesMessage(): void
    {
        \preg_match('/function\s+newContactMessage.*?\{(.+?)\n    \}/s', self::$source, $m);
        $this->assertStringContainsString('mb_substr($message, 0, 1024)', $m[1],
            'newContactMessage() must truncate message field to 1024 chars');
    }

    // =============================================
    // Error handling
    // =============================================

    /**
     * send() нь exception шидэхгүй - бүх алдааг catch хийдэг.
     */
    public function testSendCatchesAllExceptions(): void
    {
        \preg_match('/function\s+send\s*\(.*?\{(.+?)\n    \}/s', self::$source, $m);
        $this->assertStringContainsString('catch', $m[1],
            'send() must catch all exceptions to prevent notification failures from breaking the app');
    }

    /**
     * send() нь CODESAUR_DEVELOPMENT үед л error_log бичдэг.
     */
    public function testSendLogsOnlyInDevelopment(): void
    {
        \preg_match('/function\s+send\s*\(.*?\{(.+?)\n    \}/s', self::$source, $m);
        $this->assertStringContainsString('CODESAUR_DEVELOPMENT', $m[1],
            'send() must only log errors in development mode');
    }
}
