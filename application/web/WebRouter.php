<?php

namespace Web;

use codesaur\Router\Router;

/**
 * Class WebRouter
 * ---------------------------------------------------------------
 * Web Layer-ийн үндсэн маршрут тодорхойлогч класс.
 *
 * Энэ Router нь олон нийтэд харагдах вэб сайтын бүх хуудсуудын
 * HTTP маршрутуудыг бүртгэнэ.
 *
 * Session write шаардлагатай route-ууд /session/ prefix-тэй.
 * SessionMiddleware нь str_starts_with($path, '/session/') ашиглан
 * session lock-г зөвхөн шаардлагатай үед нээнэ.
 *
 * @package Web
 */
class WebRouter extends Router
{
    /**
     * Website маршрутуудыг бүртгэх.
     */
    public function __construct()
    {
        // Нүүр хуудас
        $this->GET('/', [HomeController::class, 'index'])->name('home');
        $this->GET('/home', [HomeController::class, 'index']);

        // Динамик Page (ID-аар болон slug-аар)
        $this->GET('/page/{uint:id}', [Content\PageController::class, 'pageById']);
        $this->GET('/page/{slug}', [Content\PageController::class, 'page'])->name('page');

        // Контакт пэйж
        $this->GET('/contact', [Service\ContactController::class, 'contact'])->name('contact');

        // Динамик News (ID-аар болон slug-аар)
        $this->GET('/news/{uint:id}', [Content\NewsController::class, 'newsById']);
        $this->GET('/news/{slug}', [Content\NewsController::class, 'news'])->name('news');

        // Мэдээний төрлөөр жагсаалт
        $this->GET('/news/type/{type}', [Content\NewsController::class, 'newsType'])->name('news-type');

        // Архив
        $this->GET('/archive', [Content\NewsController::class, 'archive'])->name('archive');

        // Бүтээгдэхүүнүүд (жагсаалт)
        $this->GET('/products', [Shop\ShopController::class, 'products']);

        // Динамик Product (ID-аар болон slug-аар)
        $this->GET('/product/{uint:id}', [Shop\ShopController::class, 'productById']);
        $this->GET('/product/{slug}', [Shop\ShopController::class, 'product'])->name('product');

        // Захиалгын форм (GET нь session write шаардахгүй)
        $this->GET('/order', [Shop\ShopController::class, 'order'])->name('order');

        // Хайлт
        $this->GET('/search', [Service\SearchController::class, 'search'])->name('search');

        // Сайтын бүтэц (хэрэглэгчдэд)
        $this->GET('/sitemap', [Service\SeoController::class, 'sitemap'])->name('sitemap');

        // XML Sitemap (SEO)
        $this->GET('/sitemap.xml', [Service\SeoController::class, 'sitemapXml']);

        // RSS Feed
        $this->GET('/rss', [Service\SeoController::class, 'rss'])->name('rss');

        // Favicon
        $this->GET('/favicon.ico', [HomeController::class, 'favicon']);


        /* ---------------------------------------------------------------
         * SESSION WRITE - Дараах route-ууд $_SESSION-д бичдэг тул
         * /session/ prefix ашиглана. SessionMiddleware нь
         * str_starts_with($path, '/session/') ашиглан таниж
         * session lock-г зөвхөн эдгээр route-уудад нээнэ.
         * --------------------------------------------------------------- */

        // Хэл солих
        $this->GET('/session/language/{code}', [HomeController::class, 'language'])->name('language');

        // Холбоо барих мессеж илгээх
        $this->POST('/session/contact-send', [Service\ContactController::class, 'contactSend'])->name('contact-send');

        // Захиалга илгээх
        $this->POST('/session/order', [Shop\ShopController::class, 'orderSubmit'])->name('order-submit');

        // Мэдээний сэтгэгдэл
        $this->POST('/session/news/{uint:id}/comment', [Content\NewsController::class, 'commentSubmit'])->name('news-comment');

        // Бүтээгдэхүүний үнэлгээ
        $this->POST('/session/product/{uint:id}/review', [Shop\ShopController::class, 'reviewSubmit'])->name('product-review');
    }
}
