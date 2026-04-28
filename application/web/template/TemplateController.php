<?php

namespace Web\Template;

use codesaur\Template\FileTemplate;

use Raptor\Content\PagesModel;

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
class TemplateController extends \Raptor\Controller
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
     *   $vars['code']        -> index-д `record_code` болно
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

        // SEO meta: $vars дотроос index layout руу автоматаар map хийх
        $metaKeys = ['title' => 'record_title', 'code' => 'record_code', 'description' => 'record_description', 'photo' => 'record_photo'];
        foreach ($metaKeys as $key => $indexKey) {
            if (isset($vars[$key]) && $vars[$key] !== '') {
                $index->set($indexKey, $vars[$key]);
            }
        }

        // Base URL (OG meta, canonical, share URL-д ашиглагдана)
        $uri = $this->getRequest()->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost()
            . ($uri->getPort() && !\in_array($uri->getPort(), [80, 443]) ? ':' . $uri->getPort() : '');
        $index->set('base_url', $baseUrl);
        $index->set('current_url', (string) $uri);

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
}
