<?php

namespace Web\Template;

use Twig\TwigFilter;

use codesaur\Template\TwigTemplate;

use Raptor\Content\PagesModel;

/**
 * Class TemplateController
 * ---------------------------------------------------------------
 * Raptor Framework - Web UI Template Controller
 *
 * Энэ контроллер нь вэб сайтын бүх үндсэн layout (index.html) болон
 * динамик контентуудыг TwigTemplate ашиглан нэгтгэж рендерлэх үүрэгтэй.
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
     * Web layout (index.html) + контент template нэгтгэж бэлэн TwigTemplate буцаана.
     *
     * Энэ method нь Dashboard-ийн `twigDashboard()`-тай адил үүрэгтэй:
     * layout + content template-ийг нэгтгэнэ. Дотроо `twigTemplate()`-г
     * дуудаж бүх зүйлийг бэлддэг тул caller дахин `twigTemplate()` дуудах
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
     *   $this->twigWebLayout(__DIR__ . '/page.html', $record)->render();
     *
     * @param string $template Контентын Twig template файл (жишээ: page.html)
     * @param array  $vars     Контент template-д дамжуулах хувьсагчид.
     *                         title, code, description, photo key байвал
     *                         index layout-ийн SEO meta-д автоматаар map хийгдэнэ.
     *
     * @return TwigTemplate Web-ийн бүрэн layout-тэй рендерлэхэд бэлэн объект
     */
    public function twigWebLayout(string $template, array $vars = []): TwigTemplate
    {
        $index = $this->twigTemplate(__DIR__ . '/index.html');
        $content = $this->twigTemplate($template, $vars);
        $content->addFilter(new TwigFilter('basename', fn(string $path): string => \rawurldecode(\basename($path))));
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

        // Navigation menu (сонгосон хэлээр)
        $code = $this->getLanguageCode();
        $pagesModel = new PagesModel($this->pdo);
        $index->set('main_menu', $pagesModel->getNavigation($code));
        $index->set('featured_pages', $pagesModel->getFeaturedLeafPages($code));

        return $index;
    }

}
