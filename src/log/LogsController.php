<?php

namespace Raptor\Log;

use Exception;
use Throwable;

use codesaur\Template\TwigTemplate;

use Raptor\Dashboard\DashboardController;

class LogsController extends DashboardController
{
    public function index()
    {
        $template = $this->twigDashboard($this->text('access-log'));
        
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new Exception($this->text('system-no-permission'));
            }
        
            $names = $this->indoget('/log/get/names');
            $logs = array();
            foreach ($names as $name) {
                $logs[$name] = $this->getLogsFrom($name);
            }
            
            $template->render($this->twigTemplate(dirname(__FILE__) . '/index-list-logs.html',
                    array('names' => $names, 'logs' => $logs, 'accounts' => $this->getAccounts())));
        } catch (Throwable $e) {
            $template->alertNoPermission($e->getMessage());
        }
    }
    
    public function view()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $id = $this->getQueryParam('id');
            $table = $this->getQueryParam('table');            
            if ($id == null || !is_int((int)$id) || empty($table)) {
                throw new Exception($this->text('invalid-request'));
            }
            
            $logdata = $this->indoget("/log?table=$table&id=$id");
            $template_path = dirname(__FILE__) . '/retrieve-log-modal.html';
            if (!file_exists($template_path)) {
                throw new Exception("$template_path file not found!");
            }
            (new TwigTemplate(
                $template_path,
                array(
                    'detailed' => $this->text('detailed'),
                    'close' => $this->text('close'),
                    'table' => $table,
                    'id' => $id,
                    'accounts' => $this->getAccounts(),
                    'data' => $logdata)
            ))->render();

            return true;
        } catch (Throwable $e) {
            echo $this->errorNoPermissionModal($e->getMessage());

            return false;
        }
    }
    
    function getLogsFrom(string $table, int $limit = 100)
    {
        try {
            return $this->indoget("/log?table=$table&limit=$limit");
        } catch (Throwable $e) {
            $this->errorLog($e);
            
            return array();
        }
    }
}
