<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Localization\TextModel;

use Raptor\Dashboard\DashboardController;

class TextController extends DashboardController
{
    public function index()
    {
        if (!$this->isUserCan('system_localization_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $colors = [
            'success' => 1,
            'primary' => 2,
            'danger' => 3,
            'warning' => 4,
            'info' => 5,
            'dark' => 6
        ];
        $names = $this->indoget('/text/table/names');
        $table = ['user', 'default', 'dashboard'];
        foreach ($names as $name) {
            if (\in_array($name, $table)) {
                $table[] = $name;
            }
        }
        $tables = \array_flip($table);
        foreach ($tables as &$index) {
            $index = \array_rand($colors);
            if (\count($colors) > 1) {
                unset($colors[$index]);
            }
        }
        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/texts-index.html', ['tables' => $tables]);
        $dashboard->set('title', $this->getLanguageCode() == 'mn' ? 'Текстүүд' : 'Texts');
        $dashboard->render();
        
        $this->indolog('localization', LogLevel::NOTICE, 'Системийн текст жагсаалтыг нээж үзэж байна', ['model' => TextModel::class, 'tables' => $tables]);
    }
    
    public function datatable(string $table)
    {
        try {
            $rows = [];
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $texts = $this->indoget("/text/records/$table");
            $language_codes = \array_keys($this->getLanguages());
            foreach ($texts as $record) {
                $id = $record['id'];
                
                $row = [\htmlentities($record['keyword'])];
                foreach ($language_codes as $code) {
                    $row[] = \htmlentities($record['content']['text'][$code] ?? '');
                }
                $row[] = [\htmlentities($record['type'])];
                
                $action =
                    '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('text-view', ['table' => $table, 'id' => $id]) . '"><i class="bi bi-eye"></i></a>';
                
                if ($this->getUser()->can('system_localization_update')) {
                    $action .=
                        ' <a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('text-update', ['table' => $table, 'id' => $id]) . '"><i class="bi bi-pencil-square"></i></a>';
                }
                if ($this->getUser()->can('system_localization_delete')) {
                    $action .= ' <a class="delete-text btn btn-sm btn-danger shadow-sm" href="' . "$table:$id" . '"><i class="bi bi-trash"></i></a>';
                }
                
                $row[] = $action;
                
                $rows[] = $row;
            }
        } catch (\Throwable $e) {
            $this->errorLog($e);
        } finally {
            $count = \count($rows);
            $this->respondJSON([
                'data' => $rows,
                'recordsTotal' => $count,
                'recordsFiltered' => $count,
                'draw' => (int) ($this->getQueryParams()['draw'] ?? 0)
            ]);
        }
    }
    
    public function insert(string $table)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = ['model' => TextModel::class, 'table' => $table];
            
            if (!$this->isUserCan('system_localization_insert')) {
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
                
                if (empty($record['keyword'])) {
                    throw new \Exception($this->text('invalid-values'), 400);
                }
                
                try {
                    $found = $this->indopost(
                        '/text/find/keyword',
                        ['keyword' => $record['keyword']]
                    );
                } catch (\Throwable $e) {
                    $found = [];
                }
                if (isset($found['id'])
                    && !empty($found['table'])
                ) {
                    throw new \Exception(
                        $this->text('keyword-existing') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']
                    );
                }
                
                $this->indopost("/text/$table", ['record' => $record, 'content' => $content]);
        
                $this->respondJSON([
                    'status' => 'success',
                    'href' => $this->generateLink('texts'),
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгт дээр шинэ текст [{$payload['keyword']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $this->twigTemplate(\dirname(__FILE__) . '/text-insert-modal.html', ['table' => $table])->render();
                
                $level = LogLevel::NOTICE   ;
                $message = "$table хүснэгт дээр шинэ текст үүсгэх үйлдлийг эхлүүллээ";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгт дээр шинэ текст үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view(string $table, int $id)
    {
        try {
            $context = ['id' => $id, 'model' => TextModel::class, 'table' => $table];
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $record = $this->indoget("/text/$table", ['p.id' => $id]);
            $context['record'] = $record;
            $this->twigTemplate(
                \dirname(__FILE__) . '/text-retrieve-modal.html',
                [
                    'table' => $table,
                    'record' => $record,
                    'accounts' => $this->getAccounts()
                ]
            )->render();

            $level = LogLevel::NOTICE;
            $message = "$table хүснэгтээс [{$record['keyword']}] текст мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгтээс текст мэдээллийг нээж үзэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => TextModel::class, 'table' => $table];

            if (!$this->isUserCan('system_localization_update')) {
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
                
                if (empty($record['keyword'])) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }

                try {
                    $found = $this->indopost(
                        '/text/find/keyword',
                        ['keyword' => $record['keyword']]
                    );
                } catch (\Throwable $e) {
                    $found = [];
                }
                if (isset($found['table'])
                    && isset($found['id'])
                    && (
                        (int) $found['id'] != $id
                        || $found['table'] != "localization_text_$table"
                    )
                ) {
                    throw new \Exception(
                        $this->text('keyword-existing') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']);
                }
                
                $this->indoput("/text/$table", [
                    'record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id=$id"]
                ]);
                
                $this->respondJSON([
                    'type' => 'primary',
                    'status' => 'success',
                    'href' => $this->generateLink('texts'),
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгтийн [{$record['keyword']}] текст мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indoget("/text/$table", ['p.id' => $id]);
                $this->twigTemplate(
                    \dirname(__FILE__) . '/text-update-modal.html',
                    ['record' => $record, 'table' => $table])->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "$table хүснэгтээс [{$record['keyword']}] текст мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = "$table хүснэгтээс текст мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => TextModel::class];
            
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['table'])
                || empty($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $table = $payload['table'];
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $this->indodelete("/text/$table", ['WHERE' => "id=$id"]);
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "$table хүснэгтээс [" . ($payload['name'] ?? $id) . '] текст мэдээллийг устгалаа';
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Текст мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
}
