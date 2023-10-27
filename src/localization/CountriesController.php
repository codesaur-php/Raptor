<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

use Indoraptor\Localization\CountriesModel;

use Raptor\Dashboard\DashboardController;

class CountriesController extends DashboardController
{    
    public function index()
    {
        if (!$this->isUserCan('system_localization_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/countries-index.html');
        $dashboard->set('title', $this->text('countries'));
        $dashboard->render();
        
        $this->indolog('localization', LogLevel::NOTICE, 'Дэлхийн улсуудын жагсаалтыг нээж үзэж байна', ['model' => CountriesModel::class]);
    }
    
    public function datatable()
    {
        try {
            $rows = [];
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $code = $this->getLanguageCode();
            $languages = $this->indoget('/records?model=' . CountriesModel::class);
            foreach ($languages as $record) {
                $row = [$record['id']];
                
                $row[] = \htmlentities($record['content']['title'][$code] ?? '');
                $row[] = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5.3/flags/' . \strtolower($record['id']) . '.png">';
                $row[] = \htmlentities($record['speak']);
                
                $action =
                    '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('country-view', ['id' => $record['id']]) . '"><i class="bi bi-eye"></i></a>';
                
                if ($this->getUser()->can('system_localization_update')) {
                    $action .=
                        ' <a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('country-update', ['id' => $record['id']]) . '"><i class="bi bi-pencil-square"></i></a>';
                }
                
                if ($this->getUser()->can('system_localization_delete')) {
                    $action .= ' <a class="delete-country btn btn-sm btn-danger shadow-sm" href="' . $record['id'] . '"><i class="bi bi-trash"></i></a>';
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
    
    public function insert()
    {
        try {
            $context = ['model' => CountriesModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
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
                
                if (empty($payload['id']) || empty($payload['speak'])) {
                    throw new \Exception($this->text('invalid-values'), 400);
                }
                
                $id = $this->indopost(
                    '/record?model=' . CountriesModel::class,
                    ['record' => $record, 'content' => $content]
                );
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
        
                $this->respondJSON([
                    'status' => 'success',
                    'href' => $this->generateLink('countries'),
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинэ улсын мэдээлэл [{$payload['id']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $this->twigTemplate(\dirname(__FILE__) . '/country-insert-modal.html')->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Улсын мэдээлэл үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Улсын мэдээлэл үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view(string $id)
    {
        try {
            $context = ['id' => $id, 'model' => CountriesModel::class];
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . CountriesModel::class, ['p.id' => $id]);
            $context['record'] = $record;            
            $this->twigTemplate(
                \dirname(__FILE__) . '/country-retrieve-modal.html',
                ['record' => $record, 'accounts' => $this->getAccounts()])->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['content']['title'][$this->getLanguageCode()]} улсын мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "[$id] улсын мэдээллийг нээж үзэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(string $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => CountriesModel::class];
            
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
                if (empty($payload['id']) || empty($payload['speak'])) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                $id = \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['id']);
                $updated = $this->indoput(
                    '/record?model=' . CountriesModel::class,
                    ['record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id='$id'"]]
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('countries')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['id']} улсын мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indoget('/record?model=' . CountriesModel::class, ['p.id' => $id]);                
                $this->twigTemplate(\dirname(__FILE__) . '/country-update-modal.html', ['record' => $record])->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['content']['title'][$this->getLanguageCode()]} улсын мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = "[$id] улсын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => CountriesModel::class];
            
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id']) || !isset($payload['name'])) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['id']);
            $deleted = $this->indodelete('/record?model=' . CountriesModel::class, ['WHERE' => "id='$id'"]);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} улсын мэдээллийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Улсын мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
}
