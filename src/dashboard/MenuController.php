<?php

namespace Raptor\Dashboard;

use Psr\Log\LogLevel;

class MenuController extends DashboardController
{
    public function userOption()
    {
        $this->twigTemplate(\dirname(__FILE__) . '/user-option-modal.html')->render();
    }
    
    public function manage()
    {
        try {
            $context = ['model' => MenuModel::class];
            
            if (!$this->isUserCan('system_manage_menu')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $menu = $this->indo(
                '/records?model=' . MenuModel::class,
                ['ORDER BY' => 'p.position', 'WHERE' => 'p.is_active=1']
            );
            
            $accounts = $this->getRBACAccounts();
            foreach ($menu as &$item) {
                if (isset($accounts[$item['created_by']])) {
                    $item['created_by'] = $accounts[$item['created_by']];
                }
                if (isset($accounts[$item['updated_by']])) {
                    $item['updated_by'] = $accounts[$item['updated_by']];
                }
            }
            
            $aliases = ['common'];
            $alias_results = $this->indo('/execute/fetch/all', [
                'query' => "SELECT alias FROM indo_organizations WHERE alias!='common' AND is_active=1 GROUP BY alias"
            ]);
            foreach ($alias_results as $row) {
               $aliases[] = $row['alias'];
            }
            
            $permissions = [];
            $permission_results = $this->indo('/execute/fetch/all', [
                'query' => "SELECT CONCAT(alias, '_', name) as permission FROM rbac_permissions WHERE is_active=1"
            ]);
            foreach ($permission_results as $row) {
               $permissions[] = $row['permission'];
            }
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/manage-menu.html',
                ['menu' => $menu, 'aliases' => $aliases, 'permissions' => $permissions]
            );
            $dashboard->render();
            
            $message = 'Цэсний жагсаалтыг нээж үзэж байна';
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = 'Цэсний жагсаалтыг нээж үзэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            if (isset($aliases)) {
                $context['aliases'] = $aliases;
            }
            if (isset($permissions)) {
                $context['permissions'] = $permissions;
            }
            if (isset($menu)) {
                $context['menu'] = $menu;
            }
            $this->indolog('dashboard', $level ?? LogLevel::NOTICE, $message, $context);
        }
    }
    
    public function insert()
    {
        try {
            $context = ['model' => MenuModel::class];
            
            if (!$this->isUserCan('system_manage_menu')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = [];
            $content = [];
            $payload = $this->getParsedBody();
            foreach ($payload as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;
                    }
                } else {
                    $record[$index] = $value;
                }
            }
            $record['is_visible'] = ($record['is_visible'] ?? 'off' ) == 'on' ? 1 : 0;
            
            $context['record'] = $record;
            $context['content'] = $content;
            if (empty($record) || empty($content)) {
                throw new \Exception($this->text('invalid-request'), 400);
            }

            $id = $this->indopost(
                '/record?model=' . MenuModel::class,
                ['record' => $record, 'content' => $content]
            );
            $context['id'] = $id;

            $this->respondJSON([
                'status' => 'success',
                'message' => $this->text('record-insert-success')
            ]);

            $level = LogLevel::INFO;
            $message = "Цэс [{$content[$this->getLanguageCode()]['title']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            $level = LogLevel::ERROR;
            $message = 'Цэс үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function update()
    {
        try {
            $context = ['model' => MenuModel::class];

            if (!$this->isUserCan('system_manage_menu')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = [];
            $content = [];
            $payload = $this->getParsedBody();
            foreach ($payload as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;
                    }
                } else {
                    $record[$index] = $value;
                }
            }
            $record['is_visible'] = ($record['is_visible'] ?? 'off' ) == 'on' ? 1 : 0;
            $context['payload'] = $payload;

            if (!isset($record['id'])
                || !\filter_var($record['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            unset($record['id']);
            $updated = $this->indoput(
                '/record?model=' . MenuModel::class,
                [
                    'record' => $record,
                    'content' => $content,
                    'condition' => ['WHERE' => "p.id=$id"]
                ]
            );
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            $this->respondJSON([
                'type' => 'primary',
                'status' => 'success',
                'message' => $this->text('record-update-success')
            ]);

            $level = LogLevel::INFO;
            $message = ($content[$this->getLanguageCode()]['title'] ?? $id) . ' цэсний мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ';
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Цэсний мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => MenuModel::class];
            
            if (!$this->isUserCan('system_manage_menu')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $this->indodelete('/record?model=' . MenuModel::class, ['WHERE' => "id=$id"]);
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = ($payload['caption'] ?? $id) . ' цэсийг устгалаа';
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Цэс устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('dashboard', $level, $message, $context);
        }
    }
}
