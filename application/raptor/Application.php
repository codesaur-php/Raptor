<?php

namespace Raptor;

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
 *   1) ErrorHandler           - Алдаа барих, JSON/HTML error
 *   2) MySQLConnectMiddleware - PDO холболт inject
 *   3) MigrationMiddleware    - Pending SQL migration автомат ажиллуулах
 *   4) SessionMiddleware      - PHP session удирдлага
 *   5) JWTAuthMiddleware      - JWT шалгаж User объект үүсгэх
 *   6) CsrfMiddleware         - CSRF token шалгах (POST/PUT/DELETE)
 *   7) ContainerMiddleware    - DI Container inject
 *   8) LocalizationMiddleware - Хэл, орчуулга inject
 *   9) SettingsMiddleware     - Системийн тохиргоо inject
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
 *   - TemplateRouter       -> Dashboard UI-ийн template харгалзах маршрут
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
     */
    public function __construct()
    {
        parent::__construct();

        // 1. Error handler
        $this->use(new Exception\ErrorHandler());

        // 2. Database (MySQL эсвэл PostgreSQL)
        $this->use(new MySQLConnectMiddleware());
        // $this->use(new PostgresConnectMiddleware());

        // 3. Migration (auto-run pending SQL files)
        $this->use(new Migration\MigrationMiddleware());

        // 4. Session
        $this->use(new SessionMiddleware(
            fn(string $path, string $method): bool =>
                \str_contains($path, '/login') || empty($_SESSION['CSRF_TOKEN'])
        ));

        // 5. JWT Authentication
        $this->use(new Authentication\JWTAuthMiddleware());

        // 6. CSRF Protection
        $this->use(new CsrfMiddleware());

        // 7. DI Container
        $this->use(new ContainerMiddleware());

        // 8. Localization
        $this->use(new Localization\LocalizationMiddleware());

        // 9. Settings
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
        $this->use(new Template\TemplateRouter());
    }
}
