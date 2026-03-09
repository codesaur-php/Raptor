<?php

namespace Web\Home;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Raptor\Content\NewsModel;

/**
 * Class HomeController
 * ---------------------------------------------------------------
 * Вэб сайтын нүүр хуудас болон хэл солих үйлдлүүдийн контроллер.
 *
 * Энэ контроллер нь:
 *   - Нүүр хуудас (index) рендерлэх - сүүлийн 20 мэдээтэй
 *   - Хэл солих (language) - session-д хэлний кодыг хадгалах
 *
 * @package Web\Home
 */
class HomeController extends TemplateController
{
    /**
     * Нүүр хуудсыг харуулах.
     *
     * Сүүлийн 20 нийтлэгдсэн мэдээг сонгосон хэл дээр татаж,
     * home.html template-ээр рендерлэнэ.
     *
     * @return void
     */
    public function index()
    {
        $code = $this->getLanguageCode();
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt_recent = $this->prepare(
            "SELECT id, title, slug, photo, published_at
             FROM $news_table
             WHERE is_active=1 AND published=1 AND code=:code
             ORDER BY published_at DESC
             LIMIT 20"
        );
        $recent = $stmt_recent->execute([':code' => $code])
            ? $stmt_recent->fetchAll()
            : [];
        $vars = ['recent' => $recent];

        $home = $this->template(__DIR__ . '/home.html', $vars);
        $home->render();

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code}] Нүүр хуудсыг уншиж байна',
            ['action' => 'home']
        );
    }

    /**
     * Вэб сайтын хэлийг солих.
     *
     * Хэрэглэгчийн сонгосон хэлний кодыг session-д хадгалж,
     * нүүр хуудас руу redirect хийнэ.
     *
     * @param string $code Хэлний код (жишээ: 'mn', 'en')
     * @return void
     */
    public function language(string $code)
    {
        $from = $this->getLanguageCode();
        $language = $this->getLanguages();
        if (isset($language[$code]) && $code !== $from) {
            $_SESSION['WEB_LANGUAGE_CODE'] = $code;
        }

        $script_path = $this->getScriptPath();
        $home = (string)$this->getRequest()->getUri()->withPath($script_path);
        \header("Location: $home", false, 302);
        exit;
    }
}
