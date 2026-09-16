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
     * CMS-ийн `content` талбар (news/pages/products/reference) нь moedit-ээр
     * бичсэн HTML тул filter-гүй {{ content }} гэж хэвлэвэл escape болж
     * хэрэглэгчид HTML эх код текстээр харагдана. Тиймээс content print бүр
     * зориудаар сонгосон filter-тэй байх ёстой: |raw (HTML-ээр харуулах)
     * эсвэл |e (textarea дотор засварлахад).
     *
     * Хасагдах газрууд: layout-ууд ({{ content }} нь FileTemplate объект тул
     * autoescape-д хамаарахгүй), login.html-ийн сунгах hook, log modal-ийн
     * macro параметр (log өгөгдөл escape хийгдэх ёстой).
     */
    public function testCmsContentPrintsHaveExplicitFilter(): void
    {
        $bareContentAllowed = [
            'web/template/index.html',
            'dashboard/template/dashboard.html',
            'dashboard/authentication/login.html',
            'dashboard/log/retrieve-log-modal.html',
        ];

        $violations = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$appDir));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }
            $rel = \str_replace('\\', '/', \substr($file->getPathname(), \strlen(self::$appDir) + 1));
            $src = \file_get_contents($file->getPathname());
            $pattern = '/\{\{\s*(content|[\w.]+(?:\[[\'"]\w+[\'"]\])*(?:\[[\'"]content[\'"]\]|\.content))\s*\}\}/';
            if (!\preg_match_all($pattern, $src, $m, \PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $match) {
                if ($match[1] === 'content' && \in_array($rel, $bareContentAllowed)) {
                    continue;
                }
                $violations[] = "$rel: {$match[0]}";
            }
        }

        $this->assertSame([], $violations, "CMS content prints must end with |raw (render HTML) or |e (textarea):\n" . \implode("\n", $violations));
    }

    /**
     * Autoescape нь илэрхийллийн үр дүнг escape хийдэг - template дотор
     * бичсэн string literal ч хамаарна. Тиймээс ternary/concat-аар HTML
     * эсвэл attribute үүсгэдэг print ({{ x ? '<i class="..."></i>' : '' }},
     * {{ p ? 'target="_blank"' : 'download' }}) filter-гүй бол
     * &lt;i&gt; / target=&quot;_blank&quot; болж эвдэрнэ. Ийм print бүр
     * (…)|raw (HTML-ээр гаргах) эсвэл |e (код жишээ болгон харуулах,
     * file/*-tag-modal.html) гэж зориудаар тэмдэглэгдсэн байх ёстой.
     */
    public function testLiteralHtmlInExpressionsHasExplicitFilter(): void
    {
        $violations = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$appDir));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }
            $src = \file_get_contents($file->getPathname());
            if (!\preg_match_all('/\{\{(.+?)\}\}/s', $src, $m, \PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $match) {
                $expr = \trim($match[1]);
                if (!\preg_match('/[\'"][^\'"]*(<[a-z\/]|\w+=")[^\'"]*[\'"]/', $expr)) {
                    continue;
                }
                if (\preg_match('/\|(raw|e)\s*$/', $expr)) {
                    continue;
                }
                $rel = \str_replace('\\', '/', \substr($file->getPathname(), \strlen(self::$appDir) + 1));
                $violations[] = "$rel: {$match[0]}";
            }
        }

        $this->assertSame([], $violations, "Prints building HTML from string literals must end with |raw or |e:\n" . \implode("\n", $violations));
    }

    /**
     * Settings-ийн urgent, contact, address, copyright талбарууд "HTML бичиж
     * болно" гэсэн нөхцөлтэй (settings.html, settings manual) - admin нь
     * <p>, <br>, <a> бичиж хадгалдаг. SettingsMiddleware-ийн утгуудыг
     * dashboardTemplate() / webTemplate() ижил нэртэй bare хувьсагчаар
     * template-д өгдөг тул {{ address }} гэж filter-гүй хэвлэвэл HTML эх код
     * текстээр харагдана. Ийм bare print бүр |raw-аар төгсөх ёстой (settings
     * form-ийн textarea нь record['localized'][code][...] замаар хэвлэдэг тул
     * энд хамаарахгүй).
     */
    public function testHtmlSettingsPrintsAreRaw(): void
    {
        $violations = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$appDir));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }
            $src = \file_get_contents($file->getPathname());
            if (!\preg_match_all('/\{\{\s*(urgent|contact|address|copyright)\b[^}]*\}\}/', $src, $m, \PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as $match) {
                if (!\preg_match('/\|raw\s*\}\}$/', $match[0])) {
                    $rel = \str_replace('\\', '/', \substr($file->getPathname(), \strlen(self::$appDir) + 1));
                    $violations[] = "$rel: {$match[0]}";
                }
            }
        }

        $this->assertSame([], $violations, "HTML-capable settings fields (urgent, contact, address, copyright) must be printed with |raw:\n" . \implode("\n", $violations));
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
