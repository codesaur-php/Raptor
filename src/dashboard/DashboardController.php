<?php

namespace Raptor\Dashboard;

use Twig\TwigFilter;

use Indoraptor\Account\MenuModel;

class DashboardController extends \Raptor\Controller
{
    public function index()
    {
        $this->twigDashboard()->render();
    }
    
    public function twigDashboard($title = null): DashboardTemplate
    {
        $twigTemplate = new DashboardTemplate();
        $twigTemplate->set('user', $this->getUser());
        $twigTemplate->set('localization', $this->getAttribute('localization'));
        $twigTemplate->set('request_path', rtrim($_SERVER['REQUEST_URI'], '/'));
        $twigTemplate->set('request_uri', (string)$this->getRequest()->getUri());
        $twigTemplate->addFilter(new TwigFilter('text', function ($key): string
        {
            return $this->text($key);
        }));
        $twigTemplate->addFilter(new TwigFilter('link', function ($routeName, $params = [], $is_absolute = false): string
        {
            return $this->generateLink($routeName, $params, $is_absolute);
        }));
        $twigTemplate->set('sidemenu', $this->getSideMenu());        
        $twigTemplate->title($title);        
        return $twigTemplate;
    }
    
    function getSideMenu()
    {
        $sidemenu_rows = $this->indoget('/account/get/menu');
        if (isset($sidemenu_rows['error'])) {
            $error = $sidemenu_rows['error'];
            if ($error['code'] == 404 && $error['message'] == 'Menu not defined') {
                $this->insertSideMenuDefault();
                $sidemenu_rows = $this->indoget('/account/get/menu');
            }
            if (isset($sidemenu_rows['error']['code'])) {
                $sidemenu_rows = array();
            }
        }
        
        $sidemenu = array();
        foreach ($sidemenu_rows as $row) {
            $title = $row['content']['title'][$this->getLanguageCode()];
            unset($row['content']);
            if ($row['parent_id'] == 0) {
                if (isset($sidemenu[$row['id']])) {
                    $sidemenu[$row['id']]['title'] = $title;
                } else {
                    $sidemenu[$row['id']] = array('title' => $title, 'submenu' => array());
                }
            } else {
                $row['title'] = $title;
                if (!isset($sidemenu[$row['parent_id']])) {
                    $sidemenu[$row['parent_id']] = array('title' => '', 'submenu' => array($row));
                } else {
                    $sidemenu[$row['parent_id']]['submenu'][] = $row ;
                }
            }
        }
        return $sidemenu;
    }
    
    function insertSideMenuDefault()
    {
        $pattern = '/record?model=' . MenuModel::class;
        $main = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Үндсэн'), 'en' => array('title' => 'Main')), 'record' => array('position' => '10')));
        if (isset($main['id'])) {
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хянах самбар'), 'en' => array('title' => 'Dashboard')),
                'record' => array('parent_id' => $main['id'], 'position' => '11', 'icon' => 'bi bi-easel', 'href' => $this->generateLink('home'))
            ));
            $script_path = dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
            $path = (strlen($script_path) > 1 ? $script_path : '');
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хостны эхлэл'), 'en' => array('title' => 'Host home')),
                'record' => array('parent_id' => $main['id'], 'position' => '12', 'icon' => 'bi bi-house-door', 'href' => (string)$this->getRequest()->getUri()->withPath($path))
            ));
        }
        $contents = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Агуулгууд'), 'en' => array('title' => 'Contents')), 'record' => array('position' => '200')));
        if (isset($contents['id'])) {
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хэл'), 'en' => array('title' => 'Languages')),
                'record' => array('parent_id' => $contents['id'], 'position' => '210', 'icon' => 'bi bi-flag-fill', 'href' => $this->generateLink('languages'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Орчуулга'), 'en' => array('title' => 'Translations')),
                'record' => array('parent_id' => $contents['id'], 'position' => '220', 'icon' => 'bi bi-translate', 'href' => $this->generateLink('translations'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Баримт бичиг загвар'), 'en' => array('title' => 'Document templates')),
                'record' => array('parent_id' => $contents['id'], 'position' => '230', 'icon' => 'bi bi-layout-wtf', 'href' => $this->generateLink('document-templates'))
            ));
        }
        $system = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Систем'), 'en' => array('title' => 'System')), 'record' => array('position' => '300')));
        if (isset($system['id'])) {
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хэрэглэгчид'), 'en' => array('title' => 'Accounts')),
                'record' => array('parent_id' => $system['id'], 'position' => '310', 'icon' => 'bi bi-people-fill', 'href' => $this->generateLink('accounts'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Байгууллага'), 'en' => array('title' => 'Organizations')),
                'record' => array('parent_id' => $system['id'], 'position' => '320', 'icon' => 'bi bi-bank2', 'href' => $this->generateLink('organizations'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Шууданч'), 'en' => array('title' => 'Mail carrier')),
                'record' => array('parent_id' => $system['id'], 'position' => '330', 'icon' => 'bi bi-mailbox2', 'href' => $this->generateLink('mailer'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хандалтын протокол'), 'en' => array('title' => 'Access logs')),
                'record' => array('parent_id' => $system['id'], 'position' => '340', 'icon' => 'bi bi-list-stars', 'href' => $this->generateLink('logs'))
            ));
        }
    }
}
