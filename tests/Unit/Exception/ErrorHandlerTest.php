<?php

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Raptor\Exception\ErrorHandler;

/**
 * ErrorHandler - алдааны response формат, статус код тохируулалтыг тестлэх.
 *
 * ErrorHandler нь error.html template-г ашиглаж render хийдэг.
 * Template байхгүй бол codesaur Base handler руу fallback хийнэ.
 */
class ErrorHandlerTest extends TestCase
{
    // =============================================
    // Title формат - Exception vs Error
    // =============================================

    public function testExceptionTitleContainsException(): void
    {
        $handler = new ErrorHandler();
        $exception = new \Exception('Test error', 404);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        $this->assertStringContainsString('Exception 404', $output);
    }

    public function testErrorTitleContainsError(): void
    {
        $handler = new ErrorHandler();
        // Error (TypeError, ValueError гэх мэт) нь Error class-аас удамшина
        $error = new \Error('Type mismatch', 0);

        \ob_start();
        @$handler->exception($error);
        $output = \ob_get_clean();

        // Error code 0 бол зөвхөн "Error" гэсэн title
        $this->assertStringContainsString('Error', $output);
    }

    public function testExceptionWithZeroCodeNoNumber(): void
    {
        $handler = new ErrorHandler();
        $exception = new \Exception('Something went wrong', 0);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        // Code 0 бол title-д тоо нэмэхгүй, зөвхөн "Exception"
        $this->assertStringContainsString('Exception', $output);
        // "Exception 0" байх ёсгүй
        $this->assertStringNotContainsString('Exception 0', $output);
    }

    // =============================================
    // Message escape - XSS хамгаалалт
    // =============================================

    public function testMessageIsHtmlEscaped(): void
    {
        $handler = new ErrorHandler();
        $xssMessage = '<script>alert("xss")</script>';
        $exception = new \Exception($xssMessage, 400);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        // XSS message нь escaped байх ёстой - бодит script tag биш
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testSpecialCharsEscaped(): void
    {
        $handler = new ErrorHandler();
        $message = 'Error: "test" & <value>';
        $exception = new \Exception($message, 500);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        $this->assertStringContainsString('&amp;', $output);
        $this->assertStringContainsString('&lt;value&gt;', $output);
    }

    // =============================================
    // HTTP status codes
    // =============================================

    /**
     * @dataProvider httpStatusCodeProvider
     */
    public function testExceptionTitleIncludesCode(int $code, string $expectedTitle): void
    {
        $handler = new ErrorHandler();
        $exception = new \Exception('Test', $code);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        if ($code !== 0) {
            $this->assertStringContainsString($expectedTitle, $output);
        } else {
            // Code 0 бол "Exception 0" байх ёсгүй, зөвхөн "Exception"
            $this->assertStringContainsString($expectedTitle, $output);
            $this->assertStringNotContainsString('Exception 0', $output);
        }
    }

    public static function httpStatusCodeProvider(): array
    {
        return [
            '400 Bad Request'  => [400, 'Exception 400'],
            '401 Unauthorized' => [401, 'Exception 401'],
            '403 Forbidden'    => [403, 'Exception 403'],
            '404 Not Found'    => [404, 'Exception 404'],
            '500 Server Error' => [500, 'Exception 500'],
            '429 Too Many'     => [429, 'Exception 429'],
            '0 No Code'        => [0, 'Exception'],
        ];
    }

    // =============================================
    // Output содержит message text
    // =============================================

    public function testPlainMessageAppearsInOutput(): void
    {
        $handler = new ErrorHandler();
        $exception = new \Exception('Page not found', 404);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        $this->assertStringContainsString('Page not found', $output);
    }

    public function testUnicodeMessageAppearsInOutput(): void
    {
        $handler = new ErrorHandler();
        $exception = new \Exception('Хуудас олдсонгүй', 404);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        $this->assertStringContainsString('Хуудас олдсонгүй', $output);
    }

    // =============================================
    // Error vs Exception class detection
    // =============================================

    public function testErrorClassShowsErrorTitle(): void
    {
        $handler = new ErrorHandler();
        $error = new \TypeError('Invalid type', 0);

        \ob_start();
        @$handler->exception($error);
        $output = \ob_get_clean();

        // TypeError нь Error-аас удамшдаг, Exception биш -> "Error" title
        // Title tag болон h1 tag дотор "Error" байх ёстой
        $this->assertStringContainsString('<title>Error</title>', $output);
        $this->assertStringNotContainsString('<title>Exception', $output);
    }

    public function testRuntimeExceptionShowsExceptionTitle(): void
    {
        $handler = new ErrorHandler();
        $exception = new \RuntimeException('Runtime failure', 500);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        // RuntimeException нь Exception-аас удамшдаг -> "Exception 500"
        $this->assertStringContainsString('Exception 500', $output);
    }

    // =============================================
    // Return link текст
    // =============================================

    public function testOutputContainsReturnText(): void
    {
        $handler = new ErrorHandler();
        $exception = new \Exception('Test', 404);

        \ob_start();
        @$handler->exception($exception);
        $output = \ob_get_clean();

        $this->assertStringContainsString('Return to host', $output);
    }
}
