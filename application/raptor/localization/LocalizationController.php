<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

/**
 * LocalizationController
 *
 * Нутагшуулалтын модулийн үндсэн "index" хуудас буюу
 * хэл + орчуулгын текстүүдийн төв хяналтын самбарыг харуулдаг controller.
 *
 * Гол үүрэг:
 * ----------
 * 1) RBAC эрх шалгана (system_localization_index).
 * 2) TextModel ашиглан localization_text хүснэгтээс бүх идэвхтэй
 *    текстүүдийг уншиж дамжуулна.
 * 3) LanguageModel ашиглан идэвхтэй хэлний жагсаалтыг авч ирнэ.
 * 4) localization-index.html Twig dashboard руу өгөгдөл дамжуулж render хийнэ.
 *
 * Бүх орчуулгын текстүүд нэг хүснэгтэд (localization_text + localization_text_content)
 * хадгалагддаг бөгөөд LocalizedModel-ийн 2 хүснэгтийн архитектурыг ашигладаг.
 */
class LocalizationController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Localization index - хэл болон орчуулгын текстүүдийн жагсаалтыг харуулна.
     *
     * Ажиллах дараалал:
     * -----------------
     * 1) Хэрэглэгчийн эрхийг шалгана (system_localization_index).
     * 2) TextModel ашиглан localization_text хүснэгтээс идэвхтэй текстүүдийг уншина.
     * 3) LanguageModel ашиглан идэвхтэй хэлний жагсаалтыг уншина.
     * 4) Twig dashboard руу өгөгдлийг дамжуулж UI-г render хийнэ.
     *
     * Алдаа гарвал dashboardProhibited() ашиглан алдааны dashboard үзүүлнэ.
     *
     * @return void
     */
    public function index()
    {        
        try {
            // Хэрэглэгч эрхгүй бол -> алдаа
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // Орчуулгын текстүүдийг унших
            $model = new TextModel($this->pdo);
            $texts = $model->getRows([
                'WHERE'    => 'p.is_active=1',
                'ORDER BY' => 'p.keyword'
            ]);

            // Идэвхтэй хэлнүүдийг унших
            $languages = (new LanguageModel($this->pdo))->getRows(['WHERE' => 'is_active=1']);

            // Localization dashboard render хийх
            $dashboard = $this->twigDashboard(
                __DIR__ . '/localization-index.html',
                [
                    'languages' => $languages,
                    'texts'     => $texts
                ]
            );
            $dashboard->set('title', $this->text('localization'));
            $dashboard->render();

            // Аудитын лог үлдээх
            $this->log(
                'localization',
                LogLevel::NOTICE,
                'Хэл ба Текстүүдийн жагсаалтыг үзэж байна',
                ['action' => 'localization-index']
            );
        } catch (\Throwable $err) {
            // Алдаа гарвал Dashboard хэлбэрээр харуулна
            $this->dashboardProhibited(
                $err->getMessage(),
                $err->getCode()
            )->render();
        }
    }
}
