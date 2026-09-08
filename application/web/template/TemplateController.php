<?php

namespace Web\Template;

use codesaur\Template\FileTemplate;

use Dashboard\Content\PagesModel;

/**
 * Class TemplateController
 * ---------------------------------------------------------------
 * Raptor Framework - Web UI Template Controller
 *
 * Энэ контроллер нь вэб сайтын бүх үндсэн layout (index.html) болон
 * динамик контентуудыг FileTemplate ашиглан нэгтгэж рендерлэх үүрэгтэй.
 *
 * Үндсэн боломжууд:
 * ---------------------------------------------------------------
 * Вэб хуудсын үндсэн загвар (`index.html`)-ийг ачаалах
 * Контент template-ийг index layout дотор оруулж нэгтгэх
 * System settings -> footer, SEO, branding гэх мэт template хувьсагчид
 * Олон түвшинтэй Main Menu (dynamic page tree) үүсгэх
 * Featured Pages (footer-ийн онцлох холбоосууд) үүсгэх
 *
 * Тухайн сайт нь олон хэл дээр ажиллах ба `PagesModel` дээр суурилсан
 * харагдах, нийтлэгдсэн контентуудыг navigation болгон хувиргана.
 *
 * @package Web\Template
 */
class TemplateController extends \Dashboard\Controller
{
    /**
     * Web layout (index.html) + контент template нэгтгэж бэлэн FileTemplate буцаана.
     *
     * Энэ method нь Dashboard-ийн `dashboardTemplate()`-тай адил үүрэгтэй:
     * layout + content template-ийг нэгтгэнэ. Дотроо `template()`-г
     * дуудаж бүх зүйлийг бэлддэг тул caller дахин `template()` дуудах
     * шаардлагагүй - зөвхөн энэ method-г дуудахад хангалттай.
     *
     * Ажиллах дараалал:
     * 1) index.html layout-г ачаална
     * 2) content template-г ачааж index layout дотор `{{ content }}` хувьсагчид суулгана
     * 3) $vars дотроос SEO meta (title, code, description, photo) талбаруудыг
     *    автоматаар index layout-д `record_*` prefix-ээр дамжуулна
     * 4) System settings (favicon, title, description...) дамжуулна
     * 5) Main Menu болон Featured Pages-г тухайн хэл дээр динамик байдлаар үүсгэнэ
     *
     * SEO meta автомат map:
     *   $vars['title']       -> index-д `record_title` болно
     *   $vars['code']        -> index-д `record_code` болно ('*' бол алгасна)
     *   $vars['description'] -> index-д `record_description` болно
     *   $vars['photo']       -> index-д `record_photo` болно
     *
     * Жишээ:
     *   $this->webTemplate(__DIR__ . '/page.html', $record)->render();
     *
     * @param string $template Контентын Template файл (жишээ: page.html)
     * @param array  $vars     Контент template-д дамжуулах хувьсагчид.
     *                         title, code, description, photo key байвал
     *                         index layout-ийн SEO meta-д автоматаар map хийгдэнэ.
     *
     * @return FileTemplate Web-ийн бүрэн layout-тэй рендерлэхэд бэлэн объект
     */
    public function webTemplate(string $template, array $vars = []): FileTemplate
    {
        $index = $this->template(__DIR__ . '/index.html');
        $content = $this->template($template, $vars);
        $content->addFilter('basename', fn(string $path): string => \rawurldecode(\basename($path)));
        $index->set('content', $content);

        // SEO meta: $vars дотроос index layout руу автоматаар map хийх.
        // Бүх хэлний ('*') бичлэгийн code-ийг дамжуулахгүй - <html lang="*">
        // хүчингүй тул layout одоогийн хэлний code-оо хэрэглэнэ.
        $metaKeys = ['title' => 'record_title', 'code' => 'record_code', 'description' => 'record_description', 'photo' => 'record_photo'];
        foreach ($metaKeys as $key => $indexKey) {
            if (isset($vars[$key]) && $vars[$key] !== '' && $vars[$key] !== '*') {
                $index->set($indexKey, $vars[$key]);
            }
        }

        // Base URL (OG meta, canonical, share URL-д ашиглагдана)
        $uri = $this->getRequest()->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost()
            . ($uri->getPort() && !\in_array($uri->getPort(), [80, 443]) ? ':' . $uri->getPort() : '');
        $index->set('base_url', $baseUrl);
        $index->set('current_url', (string) $uri);

        // Хэл бүрийн URL (language_urls, hreflang_urls) + canonical
        $recordCode = $vars['code'] ?? '';
        foreach ($this->localizedUrls($recordCode, $baseUrl) as $key => $value) {
            $index->set($key, $value);
        }

        // System settings (favicon, SEO, branding...)
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $index->set($key, $value);
        }

        // Navigation menu (сонгосон хэлээр, cache-тэй)
        $code = $this->getLanguageCode();
        $cache = $this->hasService('cache') ? $this->getService('cache') : null;
        $mainMenu = $cache?->get("pages_nav.$code");
        $featuredPages = $cache?->get("featured_pages.$code");
        if ($mainMenu === null || $featuredPages === null) {
            $pagesModel = new PagesModel($this->pdo);
            if ($mainMenu === null) {
                $mainMenu = $pagesModel->getNavigation($code);
                $cache?->set("pages_nav.$code", $mainMenu);
            }
            if ($featuredPages === null) {
                $featuredPages = $pagesModel->getFeaturedLeafPages($code);
                $cache?->set("featured_pages.$code", $featuredPages);
            }
        }
        $index->set('main_menu', $mainMenu);
        $index->set('featured_pages', $featuredPages);

        return $index;
    }

    /**
     * Одоогийн хуудасны хэл бүрийн URL болон SEO холбоосуудыг тооцоолох.
     *
     * Вэбийн хэл URL prefix-ээр тодорхойлогддог (index.php: default хэл
     * prefix-гүй, бусад нь /xx/). Энд одоогийн замаас script path болон mount
     * prefix-ийг зүсээд хэл бүрийн хувилбарыг үүсгэнэ:
     *
     *  - language_urls  ['mn' => '/news/x', 'en' => '/en/news/x'] - layout-ын
     *    хэлний dropdown. Тодорхой нэг хэлтэй бичлэгийн хуудсанд (code нь хэлний
     *    код, '*' биш) өөр хэлний хувилбар байхгүй тул бусад хэл нь тухайн
     *    хэлний нүүр рүү заана.
     *  - hreflang_urls  ['mn' => 'https://.../news/x', 'en' => ..., 'x-default' => ...]
     *    - зөвхөн бүх хэл дээр байдаг хуудсанд (жагсаалт, нүүр, code='*' бичлэг).
     *    Нэг хэлтэй бичлэгт хоосон - байхгүй орчуулгыг hreflang-аар зарлахгүй.
     *  - canonical_url  одоогийн хэлний URL. code='*' бичлэг хэл бүрийн URL дээр
     *    адилхан контенттой тул default хэлний URL-ийг canonical болгоно.
     *
     * @param string $recordCode Рендерлэж буй бичлэгийн code ('' бол бичлэг биш хуудас)
     * @param string $baseUrl    scheme://host[:port]
     * @return array language_urls, hreflang_urls, canonical_url
     */
    private function localizedUrls(string $recordCode, string $baseUrl): array
    {
        $languages = $this->getLanguages();
        $default = (string) \key($languages);
        $current = $this->getLanguageCode();
        $uri = $this->getRequest()->getUri();

        // Script path + mount prefix-гүй "цэвэр" зам (жишээ: /news/x)
        $scriptPath = $this->getScriptPath();
        $path = \rawurldecode($uri->getPath());
        if ($scriptPath !== '' && \str_starts_with($path, $scriptPath)) {
            $path = \substr($path, \strlen($scriptPath));
        }
        $mount = $this->getMountPath();
        if ($mount !== '' && \str_starts_with($path, $mount)) {
            $path = \substr($path, \strlen($mount));
        }
        $path = '/' . \ltrim($path, '/');
        $query = $uri->getQuery() !== '' ? '?' . $uri->getQuery() : '';

        $isFixedLanguage = $recordCode !== '' && $recordCode !== '*';
        $languageUrls = [];
        foreach ($languages as $code => $_) {
            $prefix = $code === $default ? '' : "/$code";
            $languageUrls[$code] = $isFixedLanguage && $code !== $current
                ? $scriptPath . $prefix . '/'
                : $scriptPath . $prefix . $path . $query;
        }

        $hreflangUrls = [];
        if (!$isFixedLanguage) {
            foreach ($languageUrls as $code => $url) {
                $hreflangUrls[$code] = $baseUrl . $url;
            }
            $hreflangUrls['x-default'] = $baseUrl . $languageUrls[$default];
        }

        $canonicalCode = $recordCode === '*' ? $default : $current;
        $canonicalUrl = $baseUrl . ($languageUrls[$canonicalCode] ?? $languageUrls[$default]);

        return [
            'language_urls' => $languageUrls,
            'hreflang_urls' => $hreflangUrls,
            'canonical_url' => $canonicalUrl
        ];
    }
}
