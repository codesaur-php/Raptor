<?php

namespace Raptor\Contents;

use Psr\Log\LogLevel;

use Indoraptor\Contents\ReferenceModel;
use Indoraptor\Contents\ReferenceInitial;

use Raptor\Dashboard\DashboardController;

class ReferencesController extends DashboardController
{
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        try {
            $reference_likes = $this->indo('/execute/fetch/all', ['query' => "SHOW TABLES LIKE 'reference_%'"]);
        } catch (\Throwable $e) {
            $reference_likes = [];
        }
        $names = [];
        foreach ($reference_likes as $name) {
            $names[] = \reset($name);
        }
        $references = [];
        foreach ($names as $name) {
            if (\in_array($name . '_content', $names)) {
                $references[] = \substr($name, \strlen('reference_'));
            }
        }
        $initials = \get_class_methods(ReferenceInitial::class);
        foreach ($initials as $value) {
            $initial = \substr($value, \strlen('reference_'));
            if (!\in_array($initial, $references)) {
                $references[] = $initial;
            }
        }
        $tables = ['templates' => []];
        foreach ($references as $reference) {
            if (!\in_array($reference,\array_keys($tables))) {
                $tables[$reference] = [];
            }
        }
        foreach (\array_keys($tables) as $table) {
            $tables[$table] = $this->indoget("/reference/records/$table");
        }
        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/references-index.html', ['tables' => $tables]);
        $dashboard->set('title', $this->text('reference-tables'));
        $dashboard->render();
        
        $this->indolog('content', LogLevel::NOTICE, 'Лавлах хүснэгтүүдийн жагсаалтыг нээж үзэж байна', ['model' => ReferenceModel::class]);
    }
    
    public function insert(string $table)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = ['model' => ReferenceModel::class, 'table' => $table];
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
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
                $context['payload'] = $payload;
                
                if (empty($payload['keyword'])
                    || empty($payload['category'])
                ) {
                    throw new \Exception($this->text('invalid-values'), 400);
                }
                
                $id = $this->indopost("/reference/$table", ['record' => $record, 'content' => $content]);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $context['record'] = $id;
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинээр [{$payload['keyword']}] түлхүүртэй лавлах мэдээллийг [$table] хүснэгт дээр үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/reference-insert.html', ['table' => $table]);
                $dashboard->set('title', $this->text('add-record') . ' | ' . \ucfirst($table));
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "Шинэ лавлах мэдээллийг [$table] хүснэгт дээр үйлдлийг эхлүүллээ";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = "Шинэ лавлах мэдээллийг [$table] хүснэгтэд үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function view(string $table, int $id)
    {
        try {
            $context = ['id' => $id, 'model' => ReferenceModel::class, 'table' => $table];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $records = $this->indoget("/reference/records/$table", ['WHERE' => "p.id=$id"]);
            $record = \reset($records);
            $record['rbac_accounts'] = $this->getRBACAccounts($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            if (empty($record)) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/reference-view.html',
                ['table' => $table, 'record' => $record]
            );
            $dashboard->set('title', $this->text('view-record') . ' | ' . \ucfirst($table));
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "$table хүснэгтийн $id дугаартай [{$record['keyword']}] түлхүүртэй лавлах мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгтээс $id дугаартай лавлах мэдээллийг нээж үзэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => ReferenceModel::class, 'table' => $table];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
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
                $context['payload'] = $payload;
                if (empty($payload)) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                $updated = $this->indoput("/reference/$table", [
                    'record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id=$id"]
                ]);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгтийн $id дугаартай [{$record['keyword']}] түлхүүртэй лавлах мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $records = $this->indoget("/reference/records/$table", ['WHERE' => "p.id=$id"]);
                $record = \reset($records);
                if (empty($record)) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                $record['rbac_accounts'] = $this->getRBACAccounts($record['created_by'], $record['updated_by']);
                $context['record'] = $record;
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/reference-update.html',
                    ['table' => $table, 'record' => $record]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | ' . \ucfirst($table));
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "$table хүснэгтийн $id дугаартай [{$record['keyword']}] түлхүүртэй лавлах мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = "$table хүснэгтийн $id дугаартай лавлах мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => ReferenceModel::class];
            
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            $context['payload'] = $payload;
            if (!isset($payload['id'])
                || empty($payload['table'])
                || !isset($payload['keyword'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            
            $table = $payload['table'];
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $deleted = $this->indodelete("/reference/$table", ['WHERE' => "id=$id"]);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "$table хүснэгтээс $id дугаартай [{$payload['keyword']}] түлхүүртэй лавлах мэдээллийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Лавлах мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
}
