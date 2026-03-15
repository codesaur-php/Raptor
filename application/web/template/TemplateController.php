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
     * Template layout-г контенттой нь нэгтгэж TwigTemplate объект буцаана.
     *
     * Ажиллах дараалал:
     * 1) index.html layout-г ачаална  
     * 2) content template-г ачааж index layout дотор `{{ content }}` хувьсагчид суулгана  
     * 3) System settings (favicon, title, description...) дамжуулна  
     * 4) Main Menu болон Featured Pages-г тухайн хэл дээр динамик байдлаар үүсгэнэ
     *
     * @param string $template Контентын Twig template файл (жишээ: page.html)
     * @param array  $vars     Контент template-д дамжуулах хувьсагчид
     *
     * @return TwigTemplate Web-ийн бүрэн layout-тэй рендерлэхэд бэлэн объект
     */
    public function template(string $template, array $vars = []): TwigTemplate
    {
        $index = $this->twigTemplate(__DIR__ . '/index.html');
        $content = $this->twigTemplate($template, $vars);
        $content->addFilter(new TwigFilter('basename', fn(string $path): string => \rawurldecode(\basename($path))));
        $index->set('content', $content);

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
