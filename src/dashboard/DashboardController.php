<?php

namespace Raptor\Dashboard;

use Exception;

use Psr\Log\LogLevel;

use codesaur\RBAC\Accounts;

use Indoraptor\Account\MenuModel;

class DashboardController extends \Raptor\Controller
{
    public function index()
    {
        $this->twigDashboard()->render();
    }
    
    public function twigDashboard($title = null): DashboardTemplate
    {
        $template = $this->setTemplateGlobal(new DashboardTemplate());
        
        $template->title($title);
        $template->set('sidemenu', $this->getSideMenu());        
        $template->set('system-no-permission', $this->text('system-no-permission'));
        
        return $template;
    }
    
    public function getAccounts(): array
    {
        $accounts = array();
        try {
            $rows = $this->indo('/record/rows?model=' . Accounts::class);
            foreach ($rows as $rows) {
                $accounts[$rows['id']] = $rows['username'] . ' » ' . $rows['first_name'] . ' ' . $rows['last_name'] . ' (' . $rows['email'] . ')';
            }
        } catch (Exception $e) {
            $this->errorLog($e);
        }
        return $accounts;
    }
    
    function getSideMenu()
    {
        try {
            $menu = $this->indoget('/account/get/menu');            
        } catch (Exception $e) {
            if ($e->getCode() == 404 && $e->getMessage() == 'Menu not defined') {
                $menu = $this->getDefaultMenu();
            } else {
                $menu = array();
            }
        }
        
        $sidemenu = array();
        foreach ($menu as $row) {
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
    
    function getDefaultMenu()
    {
        try {
            $pattern = '/record?model=' . MenuModel::class;

            $main_id = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Үндсэн'), 'en' => array('title' => 'Main')), 'record' => array('position' => '10')));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хянах самбар'), 'en' => array('title' => 'Dashboard')),
                'record' => array('parent_id' => $main_id, 'position' => '11', 'icon' => 'bi bi-easel', 'href' => $this->generateLink('home'))
            ));
            $script_path = dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
            $path = (strlen($script_path) > 1 ? $script_path : '');
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Нүүр хуудас'), 'en' => array('title' => 'Visit Home')),
                'record' => array('parent_id' => $main_id, 'position' => '12', 'icon' => 'bi bi-house-door', 'href' => (string)$this->getRequest()->getUri()->withPath($path) . '" target="__blank')
            ));

            $contents_id = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Агуулгууд'), 'en' => array('title' => 'Contents')), 'record' => array('position' => '200')));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хэл'), 'en' => array('title' => 'Languages')),
                'record' => array('parent_id' => $contents_id, 'position' => '280', 'icon' => 'bi bi-flag-fill', 'href' => $this->generateLink('languages'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Орчуулга'), 'en' => array('title' => 'Translations')),
                'record' => array('parent_id' => $contents_id, 'position' => '285', 'icon' => 'bi bi-translate', 'href' => $this->generateLink('translations'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Баримт бичиг загвар'), 'en' => array('title' => 'Document templates')),
                'record' => array('parent_id' => $contents_id, 'position' => '290', 'icon' => 'bi bi-layout-wtf', 'href' => $this->generateLink('document-templates'))
            ));
            
            $system_id = $this->indopost($pattern, array('content' => array('mn' => array('title' => 'Систем'), 'en' => array('title' => 'System')), 'record' => array('position' => '300')));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хэрэглэгчид'), 'en' => array('title' => 'Accounts')),
                'record' => array('parent_id' => $system_id, 'position' => '310', 'icon' => 'bi bi-people-fill', 'href' => $this->generateLink('accounts'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Байгууллагууд'), 'en' => array('title' => 'Organizations')),
                'record' => array('parent_id' => $system_id, 'position' => '320', 'icon' => 'bi bi-building', 'href' => $this->generateLink('organizations'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Тохируулгууд'), 'en' => array('title' => 'Settings')),
                'record' => array('parent_id' => $system_id, 'position' => '330', 'icon' => 'bi bi-gear-wide-connected', 'href' => $this->generateLink('settings'))
            ));
            $this->indopost($pattern, array(
                'content' => array('mn' => array('title' => 'Хандалтын протокол'), 'en' => array('title' => 'Access logs')),
                'record' => array('parent_id' => $system_id, 'position' => '340', 'icon' => 'bi bi-list-stars', 'href' => $this->generateLink('logs'))
            ));
            
            return $this->indoget('/account/get/menu');
        } catch (Exception $e) {
            $this->errorLog($e);
            
            return array();
        }
    }
    
    public function errorNoPermissionModal($content)
    {
        return
        '<div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="alert alert-danger shadow-sm fade mt-3 show" role="alert">
                        <i class="bi bi-shield-fill-exclamation" style="margin-right:6px"></i> ' . $content
                . '</div>
                </div>
                <div class="modal-footer modal-footer-solid">
                    <button class="btn btn-secondary shadow-sm" data-bs-dismiss="modal">' . $this->text('close') . '</button>
                </div>
            </div>
        </div>';
    }
    
    public function tryDeleteFile(string $filePath)
    {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
                
                $this->indolog('file', LogLevel::ALERT, "$filePath файлыг устгалаа");
            }
        } catch (Exception $ex) {
            $this->errorLog($ex);
        }
    }
}
