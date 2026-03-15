<?php

namespace Tests\Unit\Web;

use PHPUnit\Framework\TestCase;
use Web\Template\ExceptionHandler;
use Raptor\Exception\ErrorHandler;

/**
 * ExceptionHandler болон ErrorHandler дахь XSS хамгаалалтыг тестлэх.
 *
 * $message-г htmlspecialchars() ашиглан escape хийсэн эсэхийг шалгана.
 */
class ExceptionHandlerXssTest extends TestCase
{
    /**
     * Web ExceptionHandler - XSS тэмдэгтүүд escape хийгдсэн эсэх.
     */
    public function testWebExceptionHandlerEscapesHtmlInMessage(): void
    {
        $handler = new ExceptionHandler();

        $xssMessage = '<script>alert("xss")</script>';
        $exception = new \Exception($xssMessage, 400);

        ob_start();
        @$handler->exception($exception); // suppress error_log output
        $output = ob_get_clean();

        // <script> tag шууд орсон байх ёсгүй
        $this->assertStringNotContainsString('<script>', $output);
        // Escape хийгдсэн хэлбэрээр байх ёстой
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /**
     * Web ExceptionHandler - хос quote escape хийгдсэн эсэх.
     */
    public function testWebExceptionHandlerEscapesQuotes(): void
    {
        $handler = new ExceptionHandler();

        $quoteMessage = 'Test "quoted" & <tagged>';
        $exception = new \Exception($quoteMessage, 500);

        ob_start();
        @$handler->exception($exception);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<tagged>', $output);
        $this->assertStringContainsString('&amp;', $output);
        $this->assertStringContainsString('&lt;tagged&gt;', $output);
    }

    /**
     * Raptor ErrorHandler - XSS тэмдэгтүүд escape хийгдсэн эсэх.
     */
    public function testRaptorErrorHandlerEscapesHtmlInMessage(): void
    {
        $handler = new ErrorHandler();

        $xssMessage = '<img src=x onerror=alert(1)>';
        $exception = new \Exception($xssMessage, 400);

        ob_start();
        @$handler->exception($exception);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<img ', $output);
        $this->assertStringContainsString('&lt;img', $output);
    }

    /**
     * Энгийн мессеж өөрчлөгдөхгүй байх.
     */
    public function testPlainMessageRemainsReadable(): void
    {
        $handler = new ExceptionHandler();

        $plainMessage = 'Хуудас олдсонгүй';
        $exception = new \Exception($plainMessage, 404);

        ob_start();
        @$handler->exception($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Хуудас олдсонгүй', $output);
    }
}
