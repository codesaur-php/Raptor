<?php

namespace Raptor\Dashboard;

use Fig\Http\Message\StatusCodeInterface;

use codesaur\RBAC\Accounts;
use codesaur\Template\TwigTemplate;
use codesaur\Http\Message\ReasonPrhase;

class DashboardController extends \Raptor\Controller
{
    public function home()
    {
        $this->twigDashboard(\dirname(__FILE__) . '/home.html')->render();
    }
    
    public function twigDashboard(string $template, array $vars = []): TwigTemplate
    {
        $dashboard = $this->twigTemplate(\dirname(__FILE__) . '/dashboard.html');
        $dashboard->set('sidemenu', $this->getAccountMenu());
        $dashboard->set('content', $this->twigTemplate($template, $vars));
        foreach ($this->getAttribute('settings', []) as $key => $value) {
            $dashboard->set($key, $value);
        }
        return $dashboard;
    }
    
    public function dashboardProhibited(?string $alert = null, int|string $code = 0): TwigTemplate
    {
        $this->headerResponseCode($code);
        
        return $this->twigDashboard(
            \dirname(__FILE__) . '/alert-no-permission.html',
            ['alert' => $alert ?? $this->text('system-no-permission')]);
    }
    
    public function modalProhibited(?string $alert = null, int|string $code = 0): TwigTemplate
    {
        $this->headerResponseCode($code);
        
        return new TwigTemplate(
            \dirname(__FILE__) . '/modal-no-permission.html',
            ['alert' => $alert ?? $this->text('system-no-permission'), 'close' => $this->text('close')]);
    }
    
    protected function headerResponseCode(int|string $code)
    {
        if (!empty($code) && !\headers_sent()) {
            if ($code != StatusCodeInterface::STATUS_OK) {
                $status_code = "STATUS_$code";
                $reasonPhraseClass = ReasonPrhase::class;
                if (\defined("$reasonPhraseClass::$status_code")) {
                    \http_response_code($code);
                }
            }
        }
    }
    
    public function getAccounts(): array
    {
        try {
            $rows = $this->indo('/records?model=' . Accounts::class);
            $accounts = [];
            foreach ($rows as $rows) {
                $accounts[$rows['id']] = $rows['username'] . ' » ' . $rows['first_name'] . ' ' . $rows['last_name'] . ' (' . $rows['email'] . ')';
            }
            return $accounts;
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    public function getAccountMenu(): array
    {
        try {
            $has_menu_table = $this->indo(
                '/execute/fetch/all',
                [
                    'query' => "select exists(select 1 from raptor_account_menu)"
                ]
            );
        } catch (\Throwable $e) {
            $has_menu_table = [];
        }
        
        if (empty($has_menu_table) || \reset($has_menu_table[0]) == '0') {
            $this->createDefaultMenu();
        }

        try {
            $menu = $this->indo(
                '/records?model=' . MenuModel::class,
                ['ORDER BY' => 'p.position', 'WHERE' => 'p.is_active=1']
            );
        } catch (\Throwable $e) {
            $menu = [];
        }
        
        $sidemenu = [];
        foreach ($menu as $row) {
            $title = $row['content']['title'][$this->getLanguageCode()];
            if (!empty($row['permission'])
                && !$this->isUserCan($row['permission'])
            ) {
                continue;
            }
            if ($row['parent_id'] == 0) {
                if (isset($sidemenu[$row['id']])) {
                    $sidemenu[$row['id']]['title'] = $title;
                } else {
                    $sidemenu[$row['id']] = ['title' => $title, 'submenu' => []];
                }
            } else {
                unset($row['content']);
                $row['title'] = $title;
                if (!isset($sidemenu[$row['parent_id']])) {
                    $sidemenu[$row['parent_id']] = ['title' => '', 'submenu' => [$row]];
                } else {
                    $sidemenu[$row['parent_id']]['submenu'][] = $row;
                }
            }
        }
        
        foreach ($sidemenu as $key => $menu) {
            if (empty($menu['submenu'])) {
                unset($sidemenu[$key]);
             }
        }
        
        return $sidemenu;
    }
    
    protected function createDefaultMenu()
    {
        try {
            $recordMenu = '/record?model=' . MenuModel::class;
            
            $contents_id = $this->indopost($recordMenu, ['content' => ['mn' => ['title' => 'Агуулгууд'], 'en' => ['title' => 'Contents']], 'record' => ['position' => '200']]);
            $this->indopost($recordMenu, [ 
                'content' => ['mn' => ['title' => 'Хуудсууд'], 'en' => ['title' => 'Pages']],
                'record' => ['parent_id' => $contents_id, 'position' => '260', 'permission' => 'system_content_index', 'icon' => 'bi bi-book-half', 'href' => $this->generateLink('pages')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Мэдээнүүд'], 'en' => ['title' => 'News']],
                'record' => ['parent_id' => $contents_id, 'position' => '270', 'permission' => 'system_content_index', 'icon' => 'bi bi-newspaper', 'href' => $this->generateLink('news')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Файлууд'], 'en' => ['title' => 'Files']],
                'record' => ['parent_id' => $contents_id, 'position' => '275', 'permission' => 'system_content_index', 'icon' => 'bi bi-folder', 'href' => $this->generateLink('files')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Хэл'], 'en' => ['title' => 'Languages']],
                'record' => ['parent_id' => $contents_id, 'position' => '280', 'permission' => 'system_localization_index', 'icon' => 'bi bi-flag-fill', 'href' => $this->generateLink('languages')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Текстүүд'], 'en' => ['title' => 'Texts']],
                'record' => ['parent_id' => $contents_id, 'position' => '285', 'permission' => 'system_localization_index', 'icon' => 'bi bi-translate', 'href' => $this->generateLink('texts')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Лавлах хүснэгтүүд'], 'en' => ['title' => 'Reference Tables']],
                'record' => ['parent_id' => $contents_id, 'position' => '290', 'permission' => 'system_templates_index', 'icon' => 'bi bi-layout-wtf', 'href' => $this->generateLink('references')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Дэлхийн улсууд'], 'en' => ['title' => 'World countries']],
                'record' => ['parent_id' => $contents_id, 'position' => '295', 'permission' => 'system_localization_index', 'icon' => 'bi bi-flag', 'href' => $this->generateLink('countries')]
            ]);
            
            $system_id = $this->indopost($recordMenu, ['content' => ['mn' => ['title' => 'Систем'], 'en' => ['title' => 'System']], 'record' => ['position' => '300']]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Хэрэглэгчид'], 'en' => ['title' => 'Accounts']],
                'record' => ['parent_id' => $system_id, 'position' => '310', 'permission' => 'system_account_index', 'icon' => 'bi bi-people-fill', 'href' => $this->generateLink('accounts')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Байгууллагууд'], 'en' => ['title' => 'Organizations']],
                'record' => ['parent_id' => $system_id, 'position' => '320', 'permission' => 'system_organization_index', 'icon' => 'bi bi-building', 'href' => $this->generateLink('organizations')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Тохируулгууд'], 'en' => ['title' => 'Settings']],
                'record' => ['parent_id' => $system_id, 'position' => '330', 'permission' => 'system_content_settings', 'icon' => 'bi bi-gear-wide-connected', 'href' => $this->generateLink('settings')]
            ]);
            $this->indopost($recordMenu, [
                'content' => ['mn' => ['title' => 'Хандалтын протокол'], 'en' => ['title' => 'Access logs']],
                'record' => ['parent_id' => $system_id, 'position' => '340', 'permission' => 'system_logger', 'icon' => 'bi bi-list-stars', 'href' => $this->generateLink('logs')]
            ]);
        } catch (\Throwable $e) {
            $this->errorLog($e);
        }
    }
}
