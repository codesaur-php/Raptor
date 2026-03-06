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
        $index->set('main_menu', $this->getMainMenu($code));
        $index->set('featured_pages', $this->getFeaturedPages($code));

        return $index;
    }

    /**
     * Вэб сайтын Main Menu-г олон түвшний бүтэцтэйгээр үүсгэнэ.
     *
     * PagesModel::getNavigation() ашиглан parent -> child бүтэцтэй
     * навигацийн менюг буцаана.
     *
     * @param string $code Тухайн хэлний код (mn, en...)
     * @return array Бүтэцлэгдсэн меню (submenu дотор дахин хүүхэд элементтэй)
     */
    public function getMainMenu(string $code): array
    {
        return (new PagesModel($this->pdo))->getNavigation($code);
    }

    /**
     * Онцлох хуудсуудыг авах (footer-ийн чухал холбоосууд).
     *
     * is_featured=1 гэж тэмдэглэгдсэн, нийтлэгдсэн хуудсуудыг буцаана.
     * id, title, slug, link талбаруудыг авна.
     *
     * @param string $code Хэлний код
     * @return array Footer-д харуулах онцлох хуудсуудын жагсаалт (slug-аар холбоослож)
     */
    public function getFeaturedPages(string $code): array
    {
        $pages = [];
        $pages_table = (new PagesModel($this->pdo))->getName();
        $pages_query =
            'SELECT id, title, slug, link ' .
            "FROM $pages_table " .
            'WHERE code=:code AND is_active=1 AND published=1 AND is_featured=1 ' .
            'ORDER BY position, id';
        $stmt = $this->prepare($pages_query);
        $stmt->bindParam(':code', $code, \PDO::PARAM_STR);
        if ($stmt->execute() && $stmt->rowCount() > 0) {
            while ($row = $stmt->fetch()) {
                $pages[$row['id']] = $row;
            }
        }
        return $pages;
    }
}
