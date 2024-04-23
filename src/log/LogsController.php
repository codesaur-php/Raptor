<?php

namespace Raptor\Log;

use codesaur\Template\TwigTemplate;

use Raptor\Dashboard\DashboardController;

class LogsController extends DashboardController
{
    public function index()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
        
            $logs = [];
            $names = $this->indoget('/log/get/names');
            foreach ($names as $name) {
                $logs[$name] = $this->getLogsFrom($name);
            }
            $dashboard =  $this->twigDashboard(
                \dirname(__FILE__) . '/index-list-logs.html',
                [
                    'names' => $names, 'logs' => $logs,
                    'rbac_accounts' => $this->getRBACAccounts()
                ]
            );
            $dashboard->set('title', $this->text('log'));
            $dashboard->render();
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        }
    }
    
    public function view()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $params = $this->getQueryParams();
            $id = $params['id'] ?? null;
            $table = $params['table'] ?? null;
            if ($id == null || !\is_numeric($id) || empty($table)) {
                throw new \Exception($this->text('invalid-request'), 400);
            } else {
                $id = (int) $id;
            }
            
            $logdata = $this->indoget("/log?table=$table&id=$id");
            if (isset($logdata['created_by']) && $logdata['created_by'] !== null) {
                $logdata['created_by'] = $this->getRBACAccounts($logdata['created_by'])[$logdata['created_by']];
            }
            (new TwigTemplate(
                \dirname(__FILE__) . '/retrieve-log-modal.html',
                [
                    'id' => $id,
                    'table' => $table,
                    'data' => $logdata,
                    'detailed' => $this->text('detailed'),
                    'close' => $this->text('close')
                ]
            ))->render();

            return true;
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();

            return false;
        }
    }
    
    private function getLogsFrom(string $table, int $limit = 1000): array
    {
        try {
            return $this->indoget("/log?table=$table&limit=$limit");
        } catch (\Throwable $e) {
            $this->errorLog($e);
            
            return [];
        }
    }
}
