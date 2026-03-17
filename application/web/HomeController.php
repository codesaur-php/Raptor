<?php

namespace Web;

use Psr\Log\LogLevel;

use Raptor\Content\NewsModel;

use Web\Template\TemplateController;


/**
 * Class HomeController
 * ---------------------------------------------------------------
 * Вэб сайтын нүүр хуудас болон хэл солих үйлдлүүдийн контроллер.
 *
 * Энэ контроллер нь:
 *   - Нүүр хуудас (index) рендерлэх - сүүлийн 20 мэдээтэй
 *   - Хэл солих (language) - session-д хэлний кодыг хадгалах
 *
 * @package Web
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

        $this->twigWebLayout(__DIR__ . '/home.html', $vars)->render();

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code}] Нүүр хуудсыг уншиж байна',
            ['action' => 'home']
        );
    }

    /**
     * Favicon хүсэлтийг хариулах.
     *
     * Settings-д favicon тохируулсан бол тэр файл руу redirect хийнэ.
     * Байхгүй бол 204 No Content + cache header буцааж браузерийг
     * дахин хүсэлт илгээхээс сэргийлнэ.
     *
     * @return void
     */
    public function favicon()
    {
        $settings = $this->getAttribute('settings', []);
        $favicon = $settings['favicon'] ?? '';

        if (!empty($favicon)) {
            \header('Location: ' . $favicon, true, 302);
            \header('Cache-Control: public, max-age=86400');
            exit;
        }

        // Favicon тохируулаагүй бол 204 No Content буцаах
        // Cache header: браузер 7 хоног дахин хүсэхгүй
        \header('HTTP/1.1 204 No Content');
        \header('Cache-Control: public, max-age=604800');
        \header('Content-Type: image/x-icon');
        exit;
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
            $_SESSION[$this->getAttribute('localization')['session_key']] = $code;
        }

        $script_path = $this->getScriptPath();
        $home = (string)$this->getRequest()->getUri()->withPath($script_path);
        $home = \filter_var($home, \FILTER_SANITIZE_URL);
        \header('Location: ' . $home, true, 302);
        exit;
    }
}
