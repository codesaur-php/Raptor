<?php

namespace Dashboard;

/**
 * Class ContentSecurityPolicy
 *
 * HTML хуудсанд хамгийн бага (minimal) Content-Security-Policy header илгээнэ.
 *
 * --------------------------------------------------------------
 * Бодлого
 * --------------------------------------------------------------
 *  - WEB       : object-src 'none'; base-uri 'self'
 *  - DASHBOARD : object-src 'none'; base-uri 'self'; frame-ancestors 'self'
 *
 * object-src 'none' нь <object>/<embed> plugin-оор код ажиллуулахыг, base-uri
 * 'self' нь <base href>-ээр бүх relative холбоосыг гадны домэйн руу эргүүлэхийг
 * хаана. Эдгээр нь одоо байгаа хуудсыг эвдэхгүй.
 *
 * script-src / style-src / img-src / frame-src / default-src-ийг санаатайгаар
 * оруулаагүй: inline script, CDN, админы мэдээнд оруулсан YouTube / Facebook /
 * Google Maps / бусад сайтын iframe бүгд хэвээр ажиллана.
 *
 * frame-ancestors 'self' зөвхөн dashboard-д: админ панелийг гадны сайт iframe-д
 * оруулах хууль ёсны шалтгаан байхгүй, харин clickjacking (нэвтэрсэн админыг
 * хуурч товч дарахад хүргэх) эрсдэл яг энд байна. Web-д тавихгүй - сайтууд
 * бие биенээ iframe-д оруулдаг нь түгээмэл, хэн оруулахыг урьдчилж мэдэхгүй.
 *
 * --------------------------------------------------------------
 * Хаана илгээдэг вэ
 * --------------------------------------------------------------
 * Зөвхөн бүтэн HTML хуудас render хийх замд: DashboardTrait::dashboardTemplate(),
 * LoginController-ийн login / reset хуудас, Dashboard ErrorHandler (DASHBOARD);
 * TemplateController::webTemplate(), Web ExceptionHandler (WEB). Аль app вэ
 * гэдгийг тухайн render зам өөрөө мэддэг - URL-ээс таамаглахгүй.
 *
 * Critical: index.php эсвэл бүх хариунд хүрдэг middleware-д бүү шилжүүл.
 * ProtectedFilesController зэрэг нь PDF-ийг header()+readfile()-ээр inline
 * дамжуулж, dashboard-ын preview iframe-д харуулдаг - PDF хариунд
 * object-src 'none' очвол Chrome/Edge-ийн PDF viewer хоосон харагдана.
 * JSON болон файл хариунд CSP хэрэггүй.
 *
 * Critical (English): send this only from full-page HTML render paths, never from
 * index.php or an app-wide middleware - inline PDF responses (ProtectedFilesController,
 * shown in the dashboard preview iframe) render blank in the Chrome/Edge PDF viewer
 * under object-src 'none'. JSON and file responses need no CSP.
 *
 * --------------------------------------------------------------
 * Хатуу CSP хэрэгтэй төсөл
 * --------------------------------------------------------------
 * Доорх constant-уудад directive нэмж засна, эсвэл render хийхээс өмнө
 * өөрийн Content-Security-Policy header-ээ илгээнэ - send() нь аль хэдийн
 * илгээгдсэн CSP-г дарж бичихгүй.
 *
 * @package Dashboard
 */
class ContentSecurityPolicy
{
    /** Нийтийн вэбийн бодлого (frame-ancestors-гүй). */
    public const WEB = "object-src 'none'; base-uri 'self'";

    /** Dashboard-ын бодлого (clickjacking-аас хамгаалах frame-ancestors-тэй). */
    public const DASHBOARD = "object-src 'none'; base-uri 'self'; frame-ancestors 'self'";

    /**
     * CSP header илгээнэ. Header аль хэдийн илгээгдсэн (headers_sent) эсвэл
     * хариунд Content-Security-Policy аль хэдийн байвал юу ч хийхгүй.
     *
     * @param string $policy ContentSecurityPolicy::WEB эсвэл ::DASHBOARD
     * @return void
     */
    public static function send(string $policy): void
    {
        if (\headers_sent()) {
            return;
        }
        $line = self::headerLine($policy, \headers_list());
        if ($line !== null) {
            \header($line);
        }
    }

    /**
     * Илгээх header мөрийг буцаана, эсвэл CSP аль хэдийн байвал null.
     *
     * @param string   $policy      Бодлого
     * @param string[] $headersList headers_list()-ийн үр дүн
     * @return string|null
     */
    public static function headerLine(string $policy, array $headersList): ?string
    {
        foreach ($headersList as $header) {
            if (\stripos(\ltrim($header), 'content-security-policy:') === 0) {
                return null;
            }
        }
        return "Content-Security-Policy: $policy";
    }
}
