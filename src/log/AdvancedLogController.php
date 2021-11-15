<?php

namespace Raptor\Log;

use Exception;

use codesaur\Template\TwigTemplate;

use Raptor\Dashboard\DashboardController;

class AdvancedLogController extends DashboardController
{
    public function index()
    {
        $template = $this->twigDashboard($this->text('access-log'));
        
        if (!$this->isUserCan('system_log_index')) {
            return $template->alertNoPermission();
        }
        
        $vars = array('accounts' => $this->getAccounts());
        
        $names = $this->indoget('/log/get/names');
        if (isset($names['error']['code'])) {
            $names = array();
        }        
        
        $logs = array();
        foreach ($names as $name) {
            $list = $this->indoget("/log?table=$name&limit=100");
            if (isset($list['error']['code'])) {
                continue;
            }
            $logs[$name] = $list;
        }
        
        $vars['names'] = $names;
        $vars['logs'] = $logs;
        
        $template->render($this->twigTemplate(dirname(__FILE__) . '/index-list-logs.html', $vars));
    }
    
    public function view()
    {
        try {
            $id = $this->getQueryParam('id');
            $table = $this->getQueryParam('table');
            if (!$this->isUserCan('system_log_index')
                    || $id == null
                    || !is_int((int)$id)
                    || empty($table)
            ) {
                return $this->errorNoPermissionModal();
            }
            
            $logdata = $this->indoget("/log?table=$table&id=$id");            
            (new TwigTemplate(
                dirname(__FILE__) . '/retrieve-log-modal.html',
                array(
                    'detailed' => $this->text('detailed'),
                    'close' => $this->text('close'),
                    'table' => $table, 'id' => $id,
                    'accounts' => $this->getAccounts(),
                    'data' => $logdata)))->render();

            return true;
        } catch (Exception $e) {
            if (defined('CODESAUR_DEVELOPMENT')
                    && CODESAUR_DEVELOPMENT
            ) {
                error_log($e->getMessage());
            }

            return false;
        }
    }
}
