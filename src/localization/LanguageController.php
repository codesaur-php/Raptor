<?php

namespace Raptor\Localization;

use Error;
use Exception;
use Throwable;

use Psr\Log\LogLevel;

use Indoraptor\Localization\LanguageModel;
use Indoraptor\Localization\CountriesModel;

use Raptor\Dashboard\DashboardController;

class LanguageController extends DashboardController
{
    public function index()
    {
        $template = $this->twigDashboard($this->text('languages'));
        if (!$this->isUserCan('system_localization_index')) {
            return $template->alertNoPermission();
        }

        $template->render($this->twigTemplate(dirname(__FILE__) . '/languages-index.html'));
        
        $this->indolog('localization', LogLevel::NOTICE, 'Хэлний жагсаалтыг нээж үзэж байна', array('model' => LanguageModel::class));
    }
    
    public function insert()
    {
        $context = array('model' => LanguageModel::class);
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        
        try {            
            if (!$this->isUserCan('system_localization_insert')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            if ($is_submit) {
                $payload = array(
                    'copy' => $this->getPostParam('txt_copy'),
                    'code' => $this->getPostParam('txt_short'),
                    'full' => $this->getPostParam('txt_full')
                );
                
                foreach ($payload as $row) {
                    if (empty($row)) {
                        throw new Exception($this->text('invalid-request'));
                    }
                }
                $context['payload'] = $payload;
                
                
                $languages = $this->indoSafe('/language?app=common', [], 'GET');
                foreach ($languages as $key => $value) {
                    if ($payload['code'] == $key && $payload['full'] == $value) {
                        throw new Exception($this->text('lang-existing'));
                   }
                   if ($payload['code'] == $key) {
                        throw new Exception($this->text('lang-code-existing'));
                   }
                   if ($payload['full'] == $value) {
                        throw new Exception($this->text('lang-name-existing'));
                   }
                }
                
                $payload['app'] = 'common';

                $id = $this->indopost('/record?model=' . LanguageModel::class, array('record' => array('code' => $payload['code'], 'full' => $payload['full'])));
                $context['record'] = $id;
                
                $mother = $this->indoSafe('/record?model=' . LanguageModel::class, array('app' => 'common', 'code' => $payload['copy'], 'is_active' => 1));                
                if (isset($mother['code'])) {
                    $translated = $this->indoSafe('/language/copy/multimodel/content', array('from' => $mother['code'], 'to' => $payload['code']), 'POST');
                    if (is_array($translated)) {
                        $this->indolog(
                                'localization',
                                LogLevel::ALERT,
                                __CLASS__ . ' объект нь ' . $mother['code'] . ' хэлнээс ' . $payload['code'] . ' хэлийг хуулбарлан үүсгэлээ. ',
                                array('reason' => 'copy', 'translated' => $translated) + $context
                        );
                    }                
                }
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('languages')
                ));
                
                $level = LogLevel::INFO;
                $message = "Шинэ хэл [{$payload['full']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $code = preg_replace('/[^a-z]/', '', $this->getLanguageCode());
                $vars = array(
                    'countries' => $this->indoSafe('/record/rows?model=' . CountriesModel::class,
                            array('condition' => array('WHERE' => "c.code='$code'")))
                );
                $template_path = dirname(__FILE__) . '/language-insert-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!");
                }
                $this->twigTemplate($template_path, $vars)->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Шинэ хэл үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                echo $this->respondJSON(array('message' => $e->getMessage()));
            } else {
                echo $this->errorNoPermissionModal($e->getMessage());
            }
            
            $level = LogLevel::ERROR;
            $message = 'Шинэ хэл үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        $context = array('id' => $id, 'model' => LanguageModel::class);
        
        try {            
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $record = $this->indo('/record?model=' . LanguageModel::class, array('id' => $id));
            $context['record'] = $record;
            
            $template_path = dirname(__FILE__) . '/language-retrieve-modal.html';
            if (!file_exists($template_path)) {
                throw new Exception("$template_path file not found!");
            }
            $this->twigTemplate($template_path, array('record' => $record, 'accounts' => $this->getAccounts()))->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['full']} хэлний мэдээллийг нээж үзэж байна";
        } catch (Throwable $e) {
            echo $this->errorNoPermissionModal($e->getMessage());
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээллийг нээж үзэх үед алдаа гарч зогслоо байна';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        $is_submit = $this->getRequest()->getMethod() == 'PUT';
        $context = array('id' => $id, 'model' => LanguageModel::class);
        
        try {
            throw new Exception('Not Implemented');
            
            if (!$this->isUserCan('system_localization_update')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            if ($is_submit) {
                $record = array(
                    'code' => $this->getPostParam('code'),
                    'full' => $this->getPostParam('full'),
                    'app' => $this->getPostParam('app'),
                    'description' => $this->getPostParam('description')
                );
                $is_default = $this->getPostParam('is_default');
                $record['is_default'] = empty($is_default) || $is_default != 'on' ? 0 : 1;
                $context['record'] = $record;
                $context['record']['id'] = $id;

                if (empty($record['code']) || empty($record['full']) || empty($record['app'])) {
                    throw new Exception($this->text('invalid-request'));
                }

                $existingDefault = $this->indoSafe('/record?model=' . LanguageModel::class, array('is_default' => 1));
                if (isset($existingDefault['id']) && $record['is_default'] == 1) {
                    if ($existingDefault['id'] != $id) {
                        $this->indoput('/record?model=' . LanguageModel::class,
                                array('record' => array('is_default' => 0), 'condition' => ['WHERE' => "id={$existingDefault['id']}"]));
                    }
                }
                
                $this->indoput('/record?model=' . LanguageModel::class,
                        array('record' => $record, 'condition' => ['WHERE' => "id=$id"]));
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('languages')
                ));
                
                $level = LogLevel::INFO;
                $message = "{$record['full']} хэлний мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indo('/record?model=' . LanguageModel::class, array('id' => $id));
                
                $template_path = dirname(__FILE__) . '/language-update-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!");
                }
                $this->twigTemplate($template_path, array('record' => $record))->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['full']} хэлний мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (Error $err) {
            throw new Exception($err->getMessage(), $err->getCode());
        } catch (Throwable $e) {
            if ($is_submit) {
                echo $this->respondJSON(array('message' => $e->getMessage()));
            } else {
                echo $this->errorNoPermissionModal($e->getMessage());
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            $message = 'Хэлний мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо байна';
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        $context = array('model' => LanguageModel::class);
        
        try {
            if (!$this->isUserCan('system_localization_delete')) {
                throw new Exception('No permission for an action [delete]!');
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                    || !isset($payload['name'])
                    || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'));
            }
            $context['payload'] = $payload;
            
            $table = '';
            if (!empty($payload['table'])) {
                $table = "table={$payload['table']}&";
            }
            
            $this->indodelete("/record?{$table}model=" . LanguageModel::class, array('WHERE' => "id='{$payload['id']}'"));
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} хэлний мэдээллийг устгалаа";
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ));
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function datatable()
    {
        $rows = array();
        
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'));
            }
            
            $languages = $this->indo('/record/rows?model=' . LanguageModel::class);
            foreach ($languages as $record) {
                $id = $record['id'];
                $row = array($record['code']);
                
                $row[] = htmlentities($record['full']);
                $row[] = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5/flags/' . $record['code'] . '.png">';
                $row[] = htmlentities($record['app']);
                $row[] = htmlentities($record['created_at']);

                $action = '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('language-view', array('id' => $id)) . '"><i class="bi bi-eye"></i></a>' . PHP_EOL;
                if ($this->getUser()->can('system_localization_update')) {
                    $action .= '<a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                            'href="' . $this->generateLink('language-update', array('id' => $id)) . '"><i class="bi bi-pencil-square"></i></a>' . PHP_EOL;
                }
                if ($this->getUser()->can('system_localization_delete')) {
                    $action .= '<a class="delete-language btn btn-sm btn-danger shadow-sm" href="' . $id . '"><i class="bi bi-trash"></i></a>';
                }                
                $row[] = $action;
                
                $rows[] = $row;
            }
        } catch (Throwable $e) {
            $this->errorLog($e);
        } finally {
            $this->respondJSON(array(
                'data' => $rows,
                'recordsTotal' => count($rows),
                'recordsFiltered' => count($rows),
                'draw' => (int)($this->getQueryParam('draw') ?? 0)
            ));
        }
    }
}
