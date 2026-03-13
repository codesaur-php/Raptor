<?php

namespace Web\Home;

use codesaur\Router\Router;

/**
 * Class HomeRouter
 * ---------------------------------------------------------------
 * Web Layer-ийн үндсэн маршрут тодорхойлогч класс.
 *
 * Энэ Router нь олон нийтэд харагдах вэб сайтын бүх хуудсуудын
 * HTTP маршрутуудыг бүртгэнэ.
 *
 * Бүртгэгдсэн маршрутууд:
 *   - / , /home             -> Нүүр хуудас
 *   - /language/{code}      -> Хэл солих
 *   - /page/{id|slug}       -> Динамик хуудас (ID эсвэл slug-аар)
 *   - /contact              -> Холбоо барих хуудас
 *   - /news/{id|slug}       -> Мэдээ (ID эсвэл slug-аар)
 *   - /news/type/{type}     -> Мэдээний төрлөөр жагсаалт
 *   - /archive              -> Мэдээний архив
 *   - /products             -> Бүтээгдэхүүний жагсаалт
 *   - /product/{id|slug}    -> Бүтээгдэхүүн (ID эсвэл slug-аар)
 *   - /order                -> Захиалгын форм (GET/POST)
 *   - /search               -> Хайлт
 *   - /sitemap              -> HTML Sitemap
 *   - /sitemap.xml          -> XML Sitemap (SEO)
 *   - /rss                  -> RSS Feed
 *
 * @package Web\Home
 */
class HomeRouter extends Router
{
    public function __construct()
    {
        // Нүүр хуудас
        $this->GET('/', [HomeController::class, 'index'])->name('home');
        $this->GET('/home', [HomeController::class, 'index']);

        // Системийн хэл солих
        $this->GET('/language/{code}', [HomeController::class, 'language'])->name('language');

        // Динамик Page (ID-аар болон slug-аар)
        $this->GET('/page/{uint:id}', [PageController::class, 'pageById']);
        $this->GET('/page/{slug}', [PageController::class, 'page'])->name('page');

        // Контакт пэйж
        $this->GET('/contact', [PageController::class, 'contact'])->name('contact');

        // Динамик News (ID-аар болон slug-аар)
        $this->GET('/news/{uint:id}', [NewsController::class, 'newsById']);
        $this->GET('/news/{slug}', [NewsController::class, 'news'])->name('news');

        // Мэдээний төрлөөр жагсаалт
        $this->GET('/news/type/{type}', [NewsController::class, 'newsType'])->name('news-type');

        // Архив
        $this->GET('/archive', [NewsController::class, 'archive'])->name('archive');

        // Бүтээгдэхүүнүүд (жагсаалт)
        $this->GET('/products', [ShopController::class, 'products']);

        // Динамик Product (ID-аар болон slug-аар)
        $this->GET('/product/{uint:id}', [ShopController::class, 'productById']);
        $this->GET('/product/{slug}', [ShopController::class, 'product'])->name('product');

        // Захиалгын форм
        $this->GET('/order', [ShopController::class, 'order'])->name('order');
        $this->POST('/order', [ShopController::class, 'orderSubmit'])->name('order-submit');

        // Хайлт
        $this->GET('/search', [SeoController::class, 'search'])->name('search');

        // Сайтын бүтэц (хэрэглэгчдэд)
        $this->GET('/sitemap', [SeoController::class, 'sitemap'])->name('sitemap');

        // XML Sitemap (SEO)
        $this->GET('/sitemap.xml', [SeoController::class, 'sitemapXml']);

        // RSS Feed
        $this->GET('/rss', [SeoController::class, 'rss'])->name('rss');
    }
}
