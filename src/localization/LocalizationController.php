<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

use Indoraptor\Localization\LanguageModel;

use Raptor\Dashboard\DashboardController;

class LocalizationController extends DashboardController
{
    public function index()
    {        
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $texts = \array_flip($this->indoget('/text/table/names'));
            foreach (\array_keys($texts) as $table) {
                $texts[$table] = $this->indo("/text/records/$table", ['ORDER BY' => 'keyword']);
            }
            $languages = $this->indoget('/records?model=' . LanguageModel::class);
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/localization-index.html',
                ['languages' => $languages, 'texts' => $texts]
            );
            $dashboard->set('title', $this->text('localization'));
            $dashboard->render();
        } catch (\Throwable $e) {
             $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        } finally {
            $this->indolog('localization', LogLevel::NOTICE, 'Хэл ба Текстүүдийн жагсаалтыг нээж үзэж байна');
        }
    }
}
