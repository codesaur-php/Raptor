<?php

namespace Raptor\Contents;

use Psr\Log\LogLevel;

use Raptor\Dashboard\DashboardController;

class SettingsController extends DashboardController
{
    public function index()
    {
        $template = $this->twigDashboard($this->text('settings'));
        if (!$this->isUserCan('system_content_settings')) {
            return $template->alertNoPermission();
        }

        $template->render($this->twigTemplate(dirname(__FILE__) . '/settings.html'));
        
        $this->indolog('contents', LogLevel::NOTICE, 'Системийн тохируулгыг нээж үзэж байна');
    }
}
