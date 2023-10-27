<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

use Indoraptor\Localization\LanguageModel;
use Indoraptor\Localization\CountriesModel;

use Raptor\Dashboard\DashboardController;

class LanguageController extends DashboardController
{
    public function index()
    {
        if (!$this->isUserCan('system_localization_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/languages-index.html');
        $dashboard->set('title', $this->text('languages'));
        $dashboard->render();
        
        $this->indolog('localization', LogLevel::NOTICE, 'Хэлний жагсаалтыг нээж үзэж байна', ['model' => LanguageModel::class]);
    }
    
    public function datatable()
    {
        try {
            $rows = [];
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $languages = $this->indoget('/records?model=' . LanguageModel::class);
            foreach ($languages as $record) {
                $id = $record['id'];
                $row = [$record['code']];
                
                $row[] = \htmlentities($record['full']);
                $row[] = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5.3/flags/' . $record['code'] . '.png">';
                $row[] = \htmlentities($record['created_at']);

                $action =
                    '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('language-view', ['id' => $id]) . '"><i class="bi bi-eye"></i></a>';
                
                if ($this->getUser()->can('system_localization_update')) {
                    $action .=
                        ' <a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('language-update', ['id' => $id]) . '"><i class="bi bi-pencil-square"></i></a>';
                }
                
                if ($this->getUser()->can('system_localization_delete')) {
                    $action .= ' <a class="delete-language btn btn-sm btn-danger shadow-sm" href="' . $id . '"><i class="bi bi-trash"></i></a>';
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
            $context = ['model' => LanguageModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_localization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['copy'])
                    || empty($payload['short'])
                    || empty($payload['full'])
                ) {
                    throw new \Exception($this->text('invalid-values'), 400);
                }
                $context['payload'] = $payload;
                
                try {
                    $mother = $this->indo(
                        '/record?model=' . LanguageModel::class,
                        ['code' => $payload['copy'], 'is_active' => 1]
                    );
                } catch (\Throwable $e) {
                    $this->errorLog($e);
                }
                if (!isset($mother['code'])) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                try {
                    $languages = $this->indo('/language', [], 'GET');
                } catch (\Throwable $e) {
                    $languages = [];
                }
                foreach ($languages as $key => $value) {
                    if ($payload['short'] == $key && $payload['full'] == $value) {
                        throw new \Exception($this->text('lang-existing'), 403);
                   }
                   if ($payload['short'] == $key) {
                        throw new \Exception($this->text('lang-code-existing'), 403);
                   }
                   if ($payload['full'] == $value) {
                        throw new \Exception($this->text('lang-name-existing'), 403);
                   }
                }

                $id = $this->indopost('/record?model=' . LanguageModel::class, ['code' => $payload['short'], 'full' => $payload['full']]);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $context['record'] = $id;
                
                $copied = $this->indopost('/language/copy/multimodel/content', ['from' => $mother['code'], 'to' => $payload['short']]);
                if (!empty($copied)) {
                    $this->indolog(
                        'localization',
                        LogLevel::ALERT,
                        __CLASS__ . ' объект нь ' . $mother['code'] . ' хэлнээс ' . $payload['short'] . ' хэлийг хуулбарлан үүсгэлээ. ',
                        ['reason' => 'copy', 'copied' => $copied] + $context
                    );
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('languages')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинэ хэл [{$payload['full']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                try {
                    $code = \preg_replace('/[^a-z]/', '', $this->getLanguageCode());
                    $vars = [
                        'countries' => $this->indo(
                            '/records?model=' . CountriesModel::class,
                            ['condition' => ['WHERE' => "c.code='$code'"]]
                        )
                    ];
                } catch (\Throwable $e) {
                    $vars = [];
                }
                $this->twigTemplate(\dirname(__FILE__) . '/language-insert-modal.html', $vars)->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Шинэ хэл үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Шинэ хэл үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => LanguageModel::class];
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . LanguageModel::class, ['id' => $id]);
            $context['record'] = $record;
            $this->twigTemplate(
                \dirname(__FILE__) . '/language-retrieve-modal.html',
                ['record' => $record, 'accounts' => $this->getAccounts()])->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['full']} хэлний мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => LanguageModel::class];
            
            if (!$this->isUserCan('system_localization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['code'])
                    || empty($payload['full'])
                ) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                $record = [
                    'code' => $payload['code'],
                    'full' => $payload['full'],
                    'description' => $payload['description'] ?? null,
                    'is_default' => ($payload['is_default'] ?? 'off') != 'on' ? 0 : 1
                ];
                $context['record'] = $record;
                $context['record']['id'] = $id;

                try {
                    $defLanguage = $this->indo(
                        '/record?model=' . LanguageModel::class, ['is_default' => 1]
                    );
                } catch (\Throwable $e) {
                    $defLanguage = [];
                }
                
                if (isset($defLanguage['id'])
                    && $defLanguage['id'] != $id
                ) {
                    $this->indoput(
                        '/record?model=' . LanguageModel::class,
                        ['record' => ['is_default' => 0], 'condition' => ['WHERE' => "id={$defLanguage['id']}"]]
                    );
                }
                
                $updated = $this->indoput(
                    '/record?model=' . LanguageModel::class,
                    ['record' => $record, 'condition' => ['WHERE' => "id=$id"]]
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('languages')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['full']} хэлний мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indoget('/record?model=' . LanguageModel::class, ['id' => $id]);
                $this->twigTemplate(\dirname(__FILE__) . '/language-update-modal.html', ['record' => $record])->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['full']} хэлний мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Хэлний мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => LanguageModel::class];
            
            if (!$this->isUserCan('system_localization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            try {
                $defLanguage = $this->indo(
                    "/record?model=" . LanguageModel::class, ['is_default' => 1]
                );
            } catch (\Throwable $e) {
                $defLanguage = [];
            }
            if (isset($defLanguage['id'])) {
                if ($defLanguage['id'] == $id) {
                    throw new \Exception('Cannot remove default language!', 403);
                }
            }
            $deleted = $this->indodelete("/record?model=" . LanguageModel::class, ['WHERE' => "id=$id"]);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэлний мэдээллийг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
}
