<?php

namespace Dashboard;

/**
 * Class Application
 * --------------------------------------------------------------------
 * Dashboard модулийн үндсэн Application класс.
 *
 * Энэ класс нь Raptor\Application-ийг өргөтгөн,
 * тухайн системийн (эсвэл тухайн модулийн) роутеруудыг бүртгэх,
 * middleware болон component-уудыг залгах үндсэн зориулалттай.
 *
 * Ашиглалт:
 *  - Dashboard бүхий бүх HTTP маршрут (routes) эндээс эхэлнэ.
 *  - Шаардлагатай Router, ExceptionHandler, Middleware-уудыг $this->use() ашиглан бүртгэнэ.
 *  - parent::__construct() нь Raptor\Framework-ийн гол bootstrap процессыг эхлүүлнэ.
 *
 * @package Dashboard
 */
class Application extends \Raptor\Application
{
    /**
     * Application constructor.
     * ------------------------------------------------------------------
     * Dashboard модуль ажиллаж эхлэхэд хамгийн түрүүнд ачаалагдана.
     *
     * Процесс:
     *  1) parent::__construct() -> Raptor\Application үндсэн middleware (DB, Session,
     *     JWT, CSRF, Localization, Settings) болон core router-уудыг (Login, Users,
     *     RBAC, Content, Logs, Template, Badge гэх мэт) бүгдийг ачаална
     *  2) Home\HomeRouter -> Dashboard-ийн үндсэн router-ийг бүртгэнэ
     *  3) Shop\ShopRouter -> Дэлгүүрийн (products, orders, reviews) router-ийг бүртгэнэ
     *  4) Manual\ManualRouter -> Гарын авлагын router-ийг бүртгэнэ
     * Нэмэх боломж:
     *  - Хэрэв дараа нь KPIRouter, MemberRouter гэх мэт нэмэх бол
     *    $this->use(new HR\KPIRouter());
     *    $this->use(new Membership\MemberRouter());
     *    гэх мэтээр өргөтгөнө.
     */
    public function __construct()
    {
        parent::__construct();

        // Home модулийн Router-г бүртгэж байна
        $this->use(new Home\HomeRouter());

        // Shop модулийн Router (products, orders, reviews нэгтгэсэн)
        $this->use(new Shop\ShopRouter());

        // Гарын авлага
        $this->use(new Manual\ManualRouter());
    }
}
