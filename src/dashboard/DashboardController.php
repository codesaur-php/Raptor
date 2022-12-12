<?php

namespace Raptor\Dashboard;

use Throwable;

use Fig\Http\Message\StatusCodeInterface;

use codesaur\RBAC\Accounts;
use codesaur\Template\TwigTemplate;
use codesaur\Http\Message\ReasonPrhase;

class DashboardController extends \Raptor\Controller
{    
    public function index()
    {
        $this->twigDashboard(dirname(__FILE__) . '/home.html')->render();
    }
    
    public function twigDashboard(string $template, array $vars = []): TwigTemplate
    {
        $dashboard = $this->twigTemplate(dirname(__FILE__) . '/dashboard.html');
        $dashboard->set('meta', $this->getAttribute('meta'));
        $dashboard->set('sidemenu', $this->getAccountMenu());
        $dashboard->set('content', $this->twigTemplate($template, $vars));
        return $dashboard;
    }
    
    public function dashboardProhibited($alert = null, ?int $code = null): TwigTemplate
    {
        if (!empty($code) && !headers_sent()) {
            if ($code != StatusCodeInterface::STATUS_OK) {
                $status_code = "STATUS_$code";
                $reasonPhraseClass = ReasonPrhase::class;
                if (defined("$reasonPhraseClass::$status_code")) {
                    http_response_code($code);
                }
            }
        }
        
        return $this->twigDashboard(
            dirname(__FILE__) . '/alert-no-permission.html',
            array('alert' => $alert ?? $this->text('system-no-permission')));
    }
    
    public function modalProhibited($alert = null, ?int $code = null): TwigTemplate
    {
        if (!empty($code) && !headers_sent()) {
            if ($code != StatusCodeInterface::STATUS_OK) {
                $status_code = "STATUS_$code";
                $reasonPhraseClass = ReasonPrhase::class;
                if (defined("$reasonPhraseClass::$status_code")) {
                    http_response_code($code);
                }
            }
        }
        
        return new TwigTemplate(
            dirname(__FILE__) . '/modal-no-permission.html',
            array('alert' => $alert ?? $this->text('system-no-permission'), 'close' => $this->text('close')));
    }
    
    public function getAccounts(): array
    {
        $accounts = array();
        $rows = $this->indosafe('/record/rows?model=' . Accounts::class);
        if (!empty($rows)) {
            foreach ($rows as $rows) {
                $accounts[$rows['id']] = $rows['username'] . ' » ' . $rows['first_name'] . ' ' . $rows['last_name'] . ' (' . $rows['email'] . ')';
            }
        }
        return $accounts;
    }
    
    public function getAccountMenu()
    {
        $has_menu_table = $this->indosafe('/statement', array(
            'query' => "select exists(select 1 from raptor_account_menu)"));
        if (empty($has_menu_table) || reset($has_menu_table[0]) == '0') {
            $this->createDefaultMenu();
        }

        $menu = $this->indosafe('/record/rows?model=' . MenuModel::class,
            array('ORDER BY' => 'p.position', 'WHERE' => 'p.is_active=1'));
        $sidemenu = array();
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
                    $sidemenu[$row['id']] = array('title' => $title, 'submenu' => array());
                }
            } else {
                unset($row['content']);
                $row['title'] = $title;
                if (!isset($sidemenu[$row['parent_id']])) {
                    $sidemenu[$row['parent_id']] = array('title' => '', 'submenu' => array($row));
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
    
    function createDefaultMenu()
    {
        try {
            $pattern = '/record?model=' . MenuModel::class;

            $main_id = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Үндсэн'), 'en' => array('title' => 'Main')), 'record' => array('position' => '10')));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хянах самбар'), 'en' => array('title' => 'Dashboard')),
                'record' => array('parent_id' => $main_id, 'position' => '11', 'icon' => 'bi bi-easel', 'href' => $this->generateLink('home'))
            ));

            $contents_id = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Агуулгууд'), 'en' => array('title' => 'Contents')), 'record' => array('position' => '200')));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хэл'), 'en' => array('title' => 'Languages')),
                'record' => array('parent_id' => $contents_id, 'position' => '280', 'alias' => 'system', 'permission' => 'system_localization_index', 'icon' => 'bi bi-flag-fill', 'href' => $this->generateLink('languages'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Текстүүд'), 'en' => array('title' => 'Texts')),
                'record' => array('parent_id' => $contents_id, 'position' => '285', 'alias' => 'system', 'permission' => 'system_localization_index', 'icon' => 'bi bi-translate', 'href' => $this->generateLink('texts'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Баримт бичиг загвар'), 'en' => array('title' => 'Document templates')),
                'record' => array('parent_id' => $contents_id, 'position' => '290', 'alias' => 'system', 'permission' => 'system_templates_index', 'icon' => 'bi bi-layout-wtf', 'href' => $this->generateLink('document-templates'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Дэлхийн улсууд'), 'en' => array('title' => 'World countries')),
                'record' => array('parent_id' => $contents_id, 'position' => '295', 'alias' => 'system', 'permission' => 'system_localization_index', 'icon' => 'bi bi-flag', 'href' => $this->generateLink('countries'))
            ));
            
            $system_id = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Систем'), 'en' => array('title' => 'System')), 'record' => array('position' => '300')));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хэрэглэгчид'), 'en' => array('title' => 'Accounts')),
                'record' => array('parent_id' => $system_id, 'position' => '310', 'alias' => 'system', 'permission' => 'system_account_index', 'icon' => 'bi bi-people-fill', 'href' => $this->generateLink('accounts'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Байгууллагууд'), 'en' => array('title' => 'Organizations')),
                'record' => array('parent_id' => $system_id, 'position' => '320', 'alias' => 'system', 'permission' => 'system_organization_index', 'icon' => 'bi bi-building', 'href' => $this->generateLink('organizations'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Тохируулгууд'), 'en' => array('title' => 'Settings')),
                'record' => array('parent_id' => $system_id, 'position' => '330', 'alias' => 'system', 'permission' => 'system_content_settings', 'icon' => 'bi bi-gear-wide-connected', 'href' => $this->generateLink('settings'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хандалтын протокол'), 'en' => array('title' => 'Access logs')),
                'record' => array('parent_id' => $system_id, 'position' => '340', 'alias' => 'system', 'permission' => 'system_logger', 'icon' => 'bi bi-list-stars', 'href' => $this->generateLink('logs'))
            ));
        } catch (Throwable $e) {
            $this->errorLog($e);
        }
    }
}
