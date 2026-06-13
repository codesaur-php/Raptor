<?php

namespace Web;

use Psr\Http\Message\ResponseInterface;

/**
 * Class Application
 * ---------------------------------------------------------
 * Raptor Framework - Веб давхаргын үндсэн Application класс.
 *
 * Энэ класс нь таны веб системийн "үндсэн эхлэл" бөгөөд
 * HTTP Layer дээр хэрэгжих бүх Middleware болон Router-ийг
 * зөв дарааллаар бүртгэж ажиллуулдаг.
 *
 * Middleware-үүдийг дарааллаар бүртгэн идэвхжүүлнэ  
 * Template хөдөлгүүрийн Exception Handler ашиглана  
 * Өгөгдлийн сангийн холболтыг автоматаар үүсгэнэ  
 * Session, Localization, Settings зэрэг системийн суурь
 *   давхаргыг идэвхжүүлнэ  
 * Эцэст нь вебийн үндсэн маршрутыг бүртгэнэ
 *
 * ---------------------------------------------------------
 * Middleware-ийн дарааллын тайлбар
 * ---------------------------------------------------------
 * 1) **Template\ExceptionHandler**
 *    - Template ашиглан error page рендерлэх
 *    - Хэрвээ template файл байхгүй бол кодын default ExceptionHandler ажиллана
 *
 *    PDO холболт нь public_html/index.php дотор үүсгэгдэж request-д
 *    attribute болгон inject хийгдсэн байна. Web ба Dashboard аль аль
 *    нь нэг л холболтыг (`\Raptor\DatabaseConnection`) ашиглана.
 *
 * 2) **ContainerMiddleware**
 *    - Dependency Injection Container-г request attributes-д inject хийнэ
 *    - Service factory-ууд PDO-г request attribute-аас (`pdo`) шууд уншина
 *
 * 3) **SessionMiddleware**
 *    - PHP session удирдах
 *    - Хэрэглэгчийн authentication / session-based data хадгалах
 *
 * 4) **LocalizationMiddleware**
 *    - Системийн хэл (mn/en/...) тодорхойлох
 *    - Template-д localization объект дамжуулах
 *
 * 5) **SettingsMiddleware**
 *    - System settings (branding, favicon, footer, title, зэрэг)
 *    - Хуудсуудад дамжуулах болно
 *
 * ---------------------------------------------------------
 * Router бүртгэх
 * ---------------------------------------------------------
 * `WebRouter` - вэбийн үндсэн хуудсуудын маршрут  
 *    / -> /home, news, language гэх мэт  
 *
 * Хэрвээ та өөр Router нэмэх бол:
 *
 *      $this->use(new Shop\ShopRouter());
 *      $this->use(new News\NewsRouter());
 *      $this->use(new Auth\AuthRouter());
 *
 * гэх мэтээр нэмж болно.
 *
 * ---------------------------------------------------------
 * Хөгжүүлэгчид зориулсан тэмдэглэл
 * ---------------------------------------------------------
 * Application нь Middleware-үүдийг **өргөтгөх боломжтой**  
 * Router-уудыг хүссэнээрээ бүлэглэн зохион байгуулж болно  
 * Custom exception handler бичээд Application->use() ашиглан  
 *   override хийж бүртгэж болно  
 *
 * @package Web
 */
class Application extends \codesaur\Http\Application\Application
{
    /**
     * Web Application-г эхлүүлж middleware, router-уудыг бүртгэх.
     *
     * @param ResponseInterface $response Handler ResponseInterface биш төрөл
     *        буцаасан үед fallback болгон ашиглах хариуны prototype (base руу дамжина)
     */
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);

        // Template тулгуурласан Error Handler
        $this->use(new Template\ExceptionHandler());

        // Container middleware
        $this->use(new \Raptor\ContainerMiddleware());

        // Session middleware
        $this->use(new \Raptor\SessionMiddleware(
            fn(string $path, string $method): bool =>
                \str_starts_with($path, '/session/')
        ));

        // Localization middleware (mn/en ...)
        $this->use(new \Raptor\Localization\LocalizationMiddleware('WEB_LANGUAGE_CODE'));

        // System settings middleware (branding, favicon, footer...)
        $this->use(new \Raptor\Content\SettingsMiddleware());

        // Вебийн үндсэн маршрут
        $this->use(new WebRouter());
    }
}
