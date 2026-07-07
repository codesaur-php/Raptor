<?php

namespace Dashboard;

use Psr\Http\Message\ResponseInterface;

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
     *  1) parent::__construct() -> Raptor\Application үндсэн middleware (Session,
     *     JWT, CSRF, Container, Localization, Settings) болон core router-уудыг
     *     (Login, Users, RBAC, Content, Logs, Template, Badge гэх мэт) бүгдийг
     *     ачаална.
     *  2) Home\HomeRouter -> Dashboard-ийн үндсэн router-ийг бүртгэнэ
     *  3) Shop\ShopRouter -> Дэлгүүрийн (products, orders, reviews) router-ийг бүртгэнэ
     *  4) Manual\ManualRouter -> Гарын авлагын router-ийг бүртгэнэ
     *  5) Development\DevelopmentRouter -> Хөгжүүлэлтийн хүсэлтийн (dev-requests) router-ийг бүртгэнэ
     * Нэмэх боломж:
     *  - Хэрэв дараа нь шинэ модуль нэмэх бол түүний Router-г
     *    $this->use(new {Module}\{Module}Router());
     *    гэх мэтээр өргөтгөнө.
     *
     * @param ResponseInterface $response Handler ResponseInterface биш төрөл
     *        буцаасан үед fallback болгон ашиглах хариуны prototype (parent руу дамжина)
     */
    public function __construct(ResponseInterface $response)
    {
        parent::__construct($response);

        // Home модулийн Router-г бүртгэж байна
        $this->use(new Home\HomeRouter());

        // Shop модулийн Router (products, orders, reviews нэгтгэсэн)
        $this->use(new Shop\ShopRouter());

        // Гарын авлага
        $this->use(new Manual\ManualRouter());

        // Хөгжүүлэлтийн хүсэлт (dev-requests)
        $this->use(new Development\DevelopmentRouter());

        // Protected файл унших (authorizeRead() hook-той).
        $this->use(new Protected\ProtectedRouter());

        // Sidebar badge систем. BADGE_MAP/PERMISSION_MAP болон
        // orgScopedModules() тохиргоог Dashboard\Badge\BadgeController дотор засна.
        $this->use(new Badge\BadgeRouter());

        // ------------------------------------------------------------------
        // Dashboard layout-ийг өөрийн файлаар солих (заавал биш - жишээ).
        // ------------------------------------------------------------------
        // application/raptor/template/ доторх 3 layout-ийн оронд өөрийн
        // design-тай файлыг рендерт ашиглуулж болно. Raptor-ийн бодит файлууд
        // диск дээрээ огт өөрчлөгдөхгүй, хуулагдахгүй - DashboardTrait рендер
        // хийхдээ raptor-ийн файлын оронд энд бүртгэсэн файлыг уншина:
        //   - dashboard.html              (мастер layout: topbar/sidebar/main)
        //   - alert-no-permission.html    (эрхгүй үеийн бүтэн хуудасны alert)
        //   - modal-no-permission.html    (эрхгүй үеийн modal)
        //
        // overrideDashboardLayout() нь Raptor\Application дээр тодорхойлогдсон.
        // Router-ийн override()-той ижил зарчим: override нь энэ constructor-ийг
        // уншихад ил харагдана. Бүртгэсэн файл байхгүй бол шууд
        // InvalidArgumentException шидэж (fail-fast) анхны request дээр мэдэгдэнэ.
        //
        // Хэрхэн ашиглах:
        //   1) Солих гэж буй core файлаа application/raptor/template/-ээс хуулж
        //      аваад засна (жишээ нь application/dashboard/myspecial/dashboard.html
        //      болгож - фолдерын нэрийг raptor-ийн template/-ээс ялгаатай, өөрийн
        //      хүссэнээр өг). Хуулж авснаар доорх contract автоматаар хадгалагдана.
        //      Custom dashboard.html-д заавал байх зүйлс (эс тэгвэл эвдэрнэ):
        //        - {{ content }}                       - контент рендер хийгдэх цэг
        //        - <meta name="csrf-token" ...>        - үгүй бол бүх мутаци 403
        //        - <meta name="waf-body-encoding" ...> - WAF body-encoding client флаг
        //        - dashboard.js / dashboard.css        - badge, search, csrfFetch,
        //                                                org switcher, dark mode
        //      Сонголтоор (developer хүссэнээрээ): sidemenu давталт - зүүн цэс.
        //      Заавал биш, өөрийн навигацийг ямар ч хэлбэрээр бүтээж болно
        //      (RBAC-аар шүүсэн бэлэн цэс хэрэгтэй бол sidemenu давталтыг үлдээ).
        //   2) Файлаа энд бүртгэнэ (parent::__construct()-ийн дараа хаана ч болно):
        //
        //   $this->overrideDashboardLayout('dashboard.html', __DIR__ . '/myspecial/dashboard.html')
        //        ->overrideDashboardLayout('alert-no-permission.html', __DIR__ . '/myspecial/alert.html')
        //        ->overrideDashboardLayout('modal-no-permission.html', __DIR__ . '/myspecial/modal.html');
        //
        // Тэмдэглэл: login гэх мэт өөрийн route-тэй хуудсыг энд биш - түүнийг
        // router override-оор ($this->override(new MyLoginRouter())) солино.
        // Энэ map нь зөвхөн route-гүй, DashboardTrait-ийн гүнд дуудагддаг
        // дээрх 3 layout файлд зориулагдсан.
    }
}
