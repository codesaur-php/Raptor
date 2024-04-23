<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;

use Indoraptor\Localization\LanguageModel;

use Raptor\Dashboard\DashboardController;

class LanguageController extends DashboardController
{
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
                    || empty($payload['code'])
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
                    if ($payload['code'] == $key && $payload['full'] == $value) {
                        throw new \Exception($this->text('error-lang-existing'), 403);
                   }
                   if ($payload['code'] == $key) {
                        throw new \Exception($this->text('error-existing-lang-code'), 403);
                   }
                   if ($payload['full'] == $value) {
                        throw new \Exception($this->text('error-lang-name-existing'), 403);
                   }
                }

                $id = $this->indopost(
                    '/record?model=' . LanguageModel::class,
                    ['code' => $payload['code'], 'full' => $payload['full'], 'description' => $payload['description']]
                );
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $context['record'] = $id;
                
                $copied = $this->indopost('/language/copy/multimodel/content', ['from' => $mother['code'], 'to' => $payload['code']]);
                if (!empty($copied)) {
                    $this->indolog(
                        'localization',
                        LogLevel::ALERT,
                        __CLASS__ . ' объект нь ' . $mother['code'] . ' хэлнээс ' . $payload['code'] . ' хэлийг хуулбарлан үүсгэлээ. ',
                        ['reason' => 'copy', 'copied' => $copied] + $context
                    );
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
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
            $record['rbac_accounts'] = $this->getRBACAccounts($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            $this->twigTemplate(\dirname(__FILE__) . '/language-retrieve-modal.html', ['record' => $record])->render();

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
                    'message' => $this->text('record-update-success')
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
            if (!isset($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            try {
                $defLanguage = $this->indo(
                    '/record?model=' . LanguageModel::class, ['is_default' => 1]
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
