<?php

namespace Web;

use Psr\Log\LogLevel;

use Raptor\Content\NewsModel;
use Raptor\Content\ReadNewsTrait;

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
class HomeController extends Template\TemplateController
{
    use ReadNewsTrait;

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
        $cache = $this->hasService('cache') ? $this->getService('cache') : null;
        $recent = $cache?->get("recent_news.$code");
        if ($recent === null) {
            try {
                $recent = (new NewsModel($this->pdo))->getRecentPublished($code);
                $cache?->set("recent_news.$code", $recent);
            } catch (\Throwable $e) {
                if (CODESAUR_DEVELOPMENT) {
                    \error_log($e->getMessage());
                }
                $recent = [];
            }
        }
        $this->decorateReadNews($recent);
        $vars = ['recent' => $recent];

        $this->webTemplate(__DIR__ . '/home.html', $vars)->render();

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
            $this->setLanguageCode($code);
        }

        $script_path = $this->getScriptPath();
        $home = (string)$this->getRequest()->getUri()->withPath($script_path);
        $home = \filter_var($home, \FILTER_SANITIZE_URL);
        \header('Location: ' . $home, true, 302);
        exit;
    }
}
