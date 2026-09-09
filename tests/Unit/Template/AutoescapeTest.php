<?php

namespace Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

use codesaur\Template\Markup;
use codesaur\Template\FileTemplate;
use codesaur\Template\MemoryTemplate;

use Dashboard\Exception\ErrorHandler;

use Web\Template\ExceptionHandler;

/**
 * codesaur/template v5 autoescape - Raptor-ийн хэрэглээний regression тест.
 *
 * - {{ }} анхдагчаар escape хийдэг байх (багц буурсан бол энд унана)
 * - Бэлэн HTML дамжуулдаг газрууд (ErrorHandler, email body) Markup-аар
 *   safe тэмдэглэгдэж давхар escape болохгүй байх
 * - Template файлууд дотор HTML үүсгэдэг фильтрийн ард |raw байх (static scan)
 */
class AutoescapeTest extends TestCase
{
    private static string $appDir;

    public static function setUpBeforeClass(): void
    {
        self::$appDir = dirname(__DIR__, 3) . '/application';
    }

    // ---------------------------------------------------------
    // Багцын анхдагч төлөв
    // ---------------------------------------------------------

    public function testTemplatesEscapeByDefault(): void
    {
        $this->assertTrue((new FileTemplate())->isAutoEscape());

        $t = new MemoryTemplate('<p>{{ v }}</p>', ['v' => '<script>alert(1)</script>']);
        $this->assertEquals('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $t->output());
    }

    public function testNestedTemplateObjectIsNotEscaped(): void
    {
        // DashboardTrait / webTemplate(): $layout->set('content', $this->template(...))
        $content = new MemoryTemplate('<h1>{{ title }}</h1>', ['title' => 'A & B']);
        $layout = new MemoryTemplate('<main>{{ content }}</main>', ['content' => $content]);
        $this->assertEquals('<main><h1>A &amp; B</h1></main>', $layout->output());
    }

    // ---------------------------------------------------------
    // Email body: nl2br-тэй утгыг Markup-аар safe болгох загвар
    // ---------------------------------------------------------

    public function testEmailBodyPatternEscapesOnceAndKeepsLineBreaks(): void
    {
        $comment = "<b>hi</b>\nline2";
        $body = new MemoryTemplate('<td>{{ name }}</td><td>{{ comment }}</td>');
        $body->set('name', 'O\'Neil & <x>');
        $body->set('comment', new Markup(\nl2br(\htmlspecialchars($comment, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'))));

        $this->assertEquals(
            "<td>O&#039;Neil &amp; &lt;x&gt;</td><td>&lt;b&gt;hi&lt;/b&gt;<br />\nline2</td>",
            $body->output()
        );
    }

    public function testEmailSubjectWithAutoescapeOffStaysPlainText(): void
    {
        $subject = new MemoryTemplate('Order #{{ order_id }} - {{ customer_name }}');
        $subject->setAutoEscape(false);
        $subject->set('order_id', 7);
        $subject->set('customer_name', 'Tom & Jerry');
        $this->assertEquals('Order #7 - Tom & Jerry', $subject->output());
    }

    // ---------------------------------------------------------
    // ErrorHandler-ууд: Markup-аар дамжсан HTML давхар escape болохгүй
    // ---------------------------------------------------------

    public function testWebExceptionHandlerMarkupIsRenderedOnce(): void
    {
        ob_start();
        @(new ExceptionHandler())->exception(new \Exception('<x> & "q"', 400));
        $output = ob_get_clean();

        $this->assertStringContainsString('<p class="lead mb-4">', $output, 'Wrapper HTML must not be escaped');
        $this->assertStringContainsString('&lt;x&gt; &amp; &quot;q&quot;', $output, 'Message must be escaped exactly once');
        $this->assertStringNotContainsString('&amp;lt;', $output, 'Message must not be double-escaped');
    }

    public function testRaptorErrorHandlerMarkupIsRenderedOnce(): void
    {
        ob_start();
        @(new ErrorHandler())->exception(new \Exception('<img src=x>', 500));
        $output = ob_get_clean();

        $this->assertStringContainsString('<h3 style="text-align:center;color:white">', $output);
        $this->assertStringContainsString('&lt;img src=x&gt;', $output);
        $this->assertStringNotContainsString('&amp;lt;', $output);
    }

    // ---------------------------------------------------------
    // Static scan: HTML үүсгэдэг фильтрийн ард |raw заавал байх
    // ---------------------------------------------------------

    /**
     * |nl2br, |json_encode нь энгийн string буцаадаг тул autoescape-д
     * escape хийгдэнэ. Template дотор эдгээрийн ард зориудаар сонгосон
     * гаралт байх ёстой: |raw (HTML/JSON шууд), |e('js') (script доторх
     * string), |e (HTML attribute доторх JSON - data-record="...").
     */
    public function testHtmlProducingFiltersAreFollowedByRawOrEscape(): void
    {
        $violations = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$appDir));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }
            $src = \file_get_contents($file->getPathname());
            if (!\preg_match_all('/\{\{[^}]*\|(nl2br|json_encode)\b[^}]*\}\}/', $src, $m, \PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $match) {
                if (!\preg_match('/\|(raw|e|e\([\'"](js|html)[\'"]\))\s*\}\}$/', $match[0])) {
                    $rel = \substr($file->getPathname(), \strlen(self::$appDir) + 1);
                    $violations[] = "$rel: {$match[0]}";
                }
            }
        }

        $this->assertSame([], $violations, "Prints producing HTML/JSON must end with |raw, |e or |e('js'):\n" . \implode("\n", $violations));
    }

    /**
     * PHP тал: MemoryTemplate-д set() хийхийн өмнө htmlspecialchars() дуудах
     * шаардлагагүй болсон - давхар escape үүсгэнэ. nl2br-тэй хослол л
     * зөвшөөрөгдөнө (Markup-аар ороосон байх ёстой).
     */
    public function testNoManualHtmlspecialcharsBeforeTemplateSet(): void
    {
        $violations = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$appDir));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $lines = \file($file->getPathname());
            foreach ($lines as $i => $line) {
                if (\preg_match('/->set\(\s*[\'"][^\'"]+[\'"]\s*,\s*\\\\?htmlspecialchars\(/', $line)) {
                    $rel = \substr($file->getPathname(), \strlen(self::$appDir) + 1);
                    $violations[] = "$rel:" . ($i + 1);
                }
                if (\preg_match('/->set\(\s*[\'"][^\'"]+[\'"]\s*,\s*\\\\?nl2br\(/', $line)) {
                    $rel = \substr($file->getPathname(), \strlen(self::$appDir) + 1);
                    $violations[] = "$rel:" . ($i + 1) . ' (nl2br result must be wrapped in Markup)';
                }
            }
        }

        $this->assertSame([], $violations, "Autoescape handles escaping; remove manual htmlspecialchars():\n" . \implode("\n", $violations));
    }
}
