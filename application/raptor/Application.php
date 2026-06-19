<?php

namespace Raptor;

use Psr\Http\Message\ResponseInterface;

/**
 * Class Application
 *
 * Raptor (Raptor Dashboard) хэсгийн үндсэн Application bootstrap.
 *
 * Энэ анги нь codesaur\Http\Application\Application ангийг өргөтгөж,
 * Dashboard/Admin системийн бүх middleware болон router-үүдийг
 * тодорхой дарааллын дагуу бүртгэнэ.
 *
 * Middleware pipeline нь дараах дарааллаар ажиллана:
 *
 *   1) ErrorHandler             - Алдаа барих, JSON/HTML error
 *   2) MethodOverrideMiddleware - PUT/PATCH/DELETE verb-ийг header-аас сэргээх (WAF)
 *   3) BodyEncodingMiddleware   - base64 body decode (WAF)
 *   4) SessionMiddleware        - PHP session удирдлага
 *   5) JWTAuthMiddleware        - JWT шалгаж User объект үүсгэх
 *   6) ContainerMiddleware      - DI Container inject
 *   7) LocalizationMiddleware   - Хэл, орчуулга inject
 *   8) SettingsMiddleware       - Системийн тохиргоо inject
 *
 * MethodOverride ба BodyEncoding нь "request normalization" тул Session/JWT/
 * routing зэрэг method/body-д тулгуурладаг бүх давхаргаас өмнө байрлана.
 *
 * CSRF хамгаалалт нь app-wide биш - CsrfMiddleware нь mutating route бүрд
 * router дээр `->middleware([CsrfMiddleware::class])`-аар per-route наагдана.
 *
 * PDO холболт нь public_html/index.php дээр нэг л удаа үүсгэгдэж
 * request->getAttribute('pdo')-р дамжина (Web ба Dashboard ижил DB ашиглах).
 * Driver сонголт .env-ийн RAPTOR_DB_DRIVER хувьсагчаар хийгдэнэ.
 *
 * Migration-уудыг /dashboard/migrations хуудаснаас (per-user folder upload
 * + apply) ажиллуулна. State нь
 * `database/migrations/{userId}-{username}/[ran/]` бүтцээр тогтоогдоно.
 *
 * Мөн дараах router-үүдийг бүртгэж өгнө:
 *
 *   - LoginRouter          -> Нэвтрэх, гарах, signup, forgot-pw
 *   - UsersRouter          -> Хэрэглэгчийн CRUD
 *   - OrganizationRouter   -> Байгууллага + хэрэглэгчийн холбоос
 *   - RBACRouter           -> Permission / Role / RBAC удирдлага
 *   - LocalizationRouter   -> Хэл болон орчуулга
 *   - ContentsRouter       -> File, News, Page, Reference, Settings модулиуд
 *   - LogsRouter           -> Системийн логийн индекс, харах
 *   - DevelopmentRouter    -> Хөгжүүлэлтийн хүсэлт (dev-requests)
 *   - MigrationRouter      -> Database migration upload / apply
 *   - TrashRouter          -> Хогийн сав (сэргээх / бүрэн устгах)
 *   - TemplateRouter       -> Dashboard UI-ийн template харгалзах маршрут
 *   - BadgeRouter          -> Sidebar badge систем (unseen activity counts)
 *
 * Энэхүү Application нь Dashboard талын бүх маршрут + middleware-г
 * нэг дор авч, Raptor-ийн бүрэн backend pipeline-г босгодог.
 *
 * @package Raptor
 */
abstract class Application extends \codesaur\Http\Application\Application
{
    /**
     * Application constructor.
     *
     * Dashboard-ын middleware болон router-үүдийг бүртгэнэ.
     * Регистрлэгдсэн дараалал нь маш чухал -> authentication, localization,
     * settings, routing гэх мэт бүх давхаргууд pipeline бүтээнэ.
     *
     * @param ResponseInterface $response Handler ResponseInterface биш төрөл
     *        буцаасан үед fallback болгон ашиглах хариуны prototype (base руу дамжина)
     */
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);

        // 1. Error handler
        $this->use(new Exception\ErrorHandler());

        // 2. HTTP method override (PUT/PATCH/DELETE-г POST-оор tunnel хийх).
        // Зарим shared hosting (cPanel/LiteSpeed/mod_security) PUT/PATCH/DELETE
        // verb-ийг server түвшинд блоклодог тул X-HTTP-Method-Override header-аас
        // жинхэнэ method-ийг сэргээнэ. Энэ нь "request normalization" тул Session,
        // JWT, routing зэрэг method-д тулгуурладаг бүх давхаргаас өмнө ажиллах ёстой
        // (жишээ нь SessionMiddleware-ийн needsWrite closure $method-ийг хүлээж авдаг).
        $this->use(new MethodOverrideMiddleware());

        // 3. Body encoding (base64) decode. mod_security WAF нь body дахь
        // HTML/JS-төстэй агуулгыг XSS гэж андуурч 403 буцаадаг тул клиент тал
        // (RAPTOR_WAF_BODY_ENCODING=true үед) form талбаруудыг base64-аар
        // кодолж илгээдэг; энд буцааж decode хийнэ (header-gated).
        // Method-той ижил request normalization тул эрт ажиллана.
        $this->use(new BodyEncodingMiddleware());

        // 4. Session
        $this->use(new SessionMiddleware(
            fn(string $path, string $method): bool =>
                \str_contains($path, '/login') || empty($_SESSION['CSRF_TOKEN'])
        ));

        // 5. JWT Authentication
        $this->use(new Authentication\JWTAuthMiddleware());

        // 6. DI Container
        $this->use(new ContainerMiddleware());

        // 7. Localization
        $this->use(new Localization\LocalizationMiddleware());

        // 8. Settings
        $this->use(new Content\SettingsMiddleware());

        // Route mapping
        $this->use(new Authentication\LoginRouter());
        $this->use(new User\UsersRouter());
        $this->use(new Organization\OrganizationRouter());
        $this->use(new RBAC\RBACRouter());
        $this->use(new Localization\LocalizationRouter());
        $this->use(new Content\ContentsRouter());
        $this->use(new Log\LogsRouter());
        $this->use(new Development\DevelopmentRouter());
        $this->use(new Migration\MigrationRouter());
        $this->use(new Trash\TrashRouter());
        $this->use(new Template\TemplateRouter());
        $this->use(new Template\BadgeRouter());
    }
}
