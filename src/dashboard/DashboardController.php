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
        $dashboard->set('meta', $this->getAttribute('meta'));
        $dashboard->set('sidemenu', $this->getAccountMenu());
        $dashboard->set('content', $this->twigTemplate($template, $vars));
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
    
    private function headerResponseCode(int|string $code)
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
        $accounts = [];
        $rows = $this->indosafe('/records?model=' . Accounts::class);
        if (!empty($rows)) {
            foreach ($rows as $rows) {
                $accounts[$rows['id']] = $rows['username'] . ' » ' . $rows['first_name'] . ' ' . $rows['last_name'] . ' (' . $rows['email'] . ')';
            }
        }
        return $accounts;
    }
    
    public function getAccountMenu(): array
    {
        $has_menu_table = $this->indosafe('/statement', [
            'query' => "select exists(select 1 from raptor_account_menu)"
        ]);
        if (empty($has_menu_table) || \reset($has_menu_table[0]) == '0') {
            $this->createDefaultMenu();
        }

        $menu = $this->indosafe('/records?model=' . MenuModel::class,
            ['ORDER BY' => 'p.position', 'WHERE' => 'p.is_active=1']);
        $sidemenu = [];
        foreach ($menu as $row) {
            $title = $row['content']['title'][$this->getLanguageCode()];
            if (!empty($row['alias'])
                && $this->getUser()->getAlias() != $row['alias']
            ) {
                continue;
            }
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
            $pattern = '/record?model=' . MenuModel::class;

            $main_id = $this->indopost($pattern, ['content' => ['mn' => ['title' => 'Үндсэн'], 'en' => ['title' => 'Main']], 'record' => ['position' => '10']]);
            $script_path = $this->getScriptPath();
            $home_path = \rtrim($this->generateLink('home'), '/');
            if ($script_path != $home_path) {
                $this->indopost($pattern, [
                    'content' => ['mn' => ['title' => 'Нүүр хуудас'], 'en' => ['title' => 'Home page']],
                    'record' => ['parent_id' => $main_id, 'position' => '11', 'icon' => 'bi bi-house-door-fill', 'href' => $script_path]
                ]);
            }
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Хянах самбар'], 'en' => ['title' => 'Dashboard']],
                'record' => ['parent_id' => $main_id, 'position' => '12', 'icon' => 'bi bi-easel', 'href' => $home_path]
            ]);

            $contents_id = $this->indopost($pattern, ['content' => ['mn' => ['title' => 'Агуулгууд'], 'en' => ['title' => 'Contents']], 'record' => ['position' => '200']]);
            $this->indopost($pattern, [ 
                'content' => ['mn' => ['title' => 'Хуудсууд'], 'en' => ['title' => 'Pages']],
                'record' => ['parent_id' => $contents_id, 'position' => '260', 'alias' => 'system', 'permission' => 'system_content_index', 'icon' => 'bi bi-book-half', 'href' => $this->generateLink('pages')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Мэдээнүүд'], 'en' => ['title' => 'News']],
                'record' => ['parent_id' => $contents_id, 'position' => '270', 'alias' => 'system', 'permission' => 'system_content_index', 'icon' => 'bi bi-newspaper', 'href' => $this->generateLink('news')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Файлууд'], 'en' => ['title' => 'Files']],
                'record' => ['parent_id' => $contents_id, 'position' => '275', 'alias' => 'system', 'permission' => 'system_content_index', 'icon' => 'bi bi-folder', 'href' => $this->generateLink('files')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Хэл'], 'en' => ['title' => 'Languages']],
                'record' => ['parent_id' => $contents_id, 'position' => '280', 'alias' => 'system', 'permission' => 'system_localization_index', 'icon' => 'bi bi-flag-fill', 'href' => $this->generateLink('languages')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Текстүүд'], 'en' => ['title' => 'Texts']],
                'record' => ['parent_id' => $contents_id, 'position' => '285', 'alias' => 'system', 'permission' => 'system_localization_index', 'icon' => 'bi bi-translate', 'href' => $this->generateLink('texts')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Лавлах хүснэгтүүд'], 'en' => ['title' => 'Reference Tables']],
                'record' => ['parent_id' => $contents_id, 'position' => '290', 'alias' => 'system', 'permission' => 'system_templates_index', 'icon' => 'bi bi-layout-wtf', 'href' => $this->generateLink('references')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Дэлхийн улсууд'], 'en' => ['title' => 'World countries']],
                'record' => ['parent_id' => $contents_id, 'position' => '295', 'alias' => 'system', 'permission' => 'system_localization_index', 'icon' => 'bi bi-flag', 'href' => $this->generateLink('countries')]
            ]);
            
            $system_id = $this->indopost($pattern, ['content' => ['mn' => ['title' => 'Систем'], 'en' => ['title' => 'System']], 'record' => ['position' => '300']]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Хэрэглэгчид'], 'en' => ['title' => 'Accounts']],
                'record' => ['parent_id' => $system_id, 'position' => '310', 'alias' => 'system', 'permission' => 'system_account_index', 'icon' => 'bi bi-people-fill', 'href' => $this->generateLink('accounts')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Байгууллагууд'], 'en' => ['title' => 'Organizations']],
                'record' => ['parent_id' => $system_id, 'position' => '320', 'alias' => 'system', 'permission' => 'system_organization_index', 'icon' => 'bi bi-building', 'href' => $this->generateLink('organizations')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Тохируулгууд'], 'en' => ['title' => 'Settings']],
                'record' => ['parent_id' => $system_id, 'position' => '330', 'alias' => 'system', 'permission' => 'system_content_settings', 'icon' => 'bi bi-gear-wide-connected', 'href' => $this->generateLink('settings')]
            ]);
            $this->indopost($pattern, [
                'content' => ['mn' => ['title' => 'Хандалтын протокол'], 'en' => ['title' => 'Access logs']],
                'record' => ['parent_id' => $system_id, 'position' => '340', 'alias' => 'system', 'permission' => 'system_logger', 'icon' => 'bi bi-list-stars', 'href' => $this->generateLink('logs')]
            ]);
        } catch (\Throwable $e) {
            $this->errorLog($e);
        }
    }
}
