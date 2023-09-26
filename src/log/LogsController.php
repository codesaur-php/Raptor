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
                ['names' => $names, 'logs' => $logs, 'accounts' => $this->getAccounts()]
            );
            $dashboard->set('title', $this->text('access-log'));
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
            (new TwigTemplate(
                \dirname(__FILE__) . '/retrieve-log-modal.html',
                [
                    'detailed' => $this->text('detailed'),
                    'close' => $this->text('close'),
                    'table' => $table,
                    'id' => $id,
                    'accounts' => $this->getAccounts(),
                    'data' => $logdata
                ]
            ))->render();

            return true;
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();

            return false;
        }
    }
    
    private function getLogsFrom(string $table, int $limit = 100): array
    {
        try {
            return $this->indoget("/log?table=$table&limit=$limit");
        } catch (\Throwable $th) {
            $this->errorLog($th);
            
            return [];
        }
    }
}
