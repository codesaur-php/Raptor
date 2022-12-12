<?php

namespace Raptor\Localization;

use Error;
use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Localization\LanguageModel;
use Indoraptor\Localization\CountriesModel;

use Raptor\Dashboard\DashboardController;

class LanguageController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])
            && isset($localization['text']['languages'])
        ) {
            $meta['content']['title'][$localization['code']] = $localization['text']['languages'];
            $request = $request->withAttribute('meta', $meta);
        }
        
        parent::__construct($request);
    }
    
    public function index()
    {
        if (!$this->isUserCan('system_localization_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $this->twigDashboard(dirname(__FILE__) . '/languages-index.html')->render();
        
        $this->indolog('localization', LogLevel::NOTICE, 'Хэлний жагсаалтыг нээж үзэж байна', array('model' => LanguageModel::class));
    }
    
    public function insert()
    {        
        try {            
            $context = array('model' => LanguageModel::class);
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_localization_insert')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['copy'])
                    || empty($payload['short'])
                    || empty($payload['full'])
                ) {
                    throw new Exception($this->text('invalid-values'), 400);
                }
                $context['payload'] = $payload;
                
                $mother = $this->indosafe('/record?model=' . LanguageModel::class, array('code' => $payload['copy'], 'is_active' => 1));
                if (!isset($mother['code'])) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                
                $languages = $this->indosafe('/language?app=common', [], 'GET');
                foreach ($languages ?: array() as $key => $value) {
                    if ($payload['short'] == $key && $payload['full'] == $value) {
                        throw new Exception($this->text('lang-existing'), 403);
                   }
                   if ($payload['short'] == $key) {
                        throw new Exception($this->text('lang-code-existing'), 403);
                   }
                   if ($payload['full'] == $value) {
                        throw new Exception($this->text('lang-name-existing'), 403);
                   }    
                }

                $id = $this->indopost('/record?model=' . LanguageModel::class, array('record' => array('code' => $payload['short'], 'full' => $payload['full'])));
                $context['record'] = $id;
                
                $copied = $this->indosafe('/language/copy/multimodel/content', array('from' => $mother['code'], 'to' => $payload['short']), 'POST');
                if (is_array($copied)) {
                    $this->indolog(
                        'localization',
                        LogLevel::ALERT,
                        __CLASS__ . ' объект нь ' . $mother['code'] . ' хэлнээс ' . $payload['short'] . ' хэлийг хуулбарлан үүсгэлээ. ',
                        array('reason' => 'copy', 'copied' => $copied) + $context
                    );
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
                    'countries' => $this->indosafe('/record/rows?model=' . CountriesModel::class,
                        array('condition' => array('WHERE' => "c.code='$code'"))
                    )
                );
                $template_path = dirname(__FILE__) . '/language-insert-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, $vars)->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Шинэ хэл үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
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
        try {            
            $context = array('id' => $id, 'model' => LanguageModel::class);
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indo('/record?model=' . LanguageModel::class, array('id' => $id));
            $context['record'] = $record;
            
            $template_path = dirname(__FILE__) . '/language-retrieve-modal.html';
            if (!file_exists($template_path)) {
                throw new Exception("$template_path file not found!", 500);
            }
            $this->twigTemplate($template_path, array('record' => $record, 'accounts' => $this->getAccounts()))->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['full']} хэлний мэдээллийг нээж үзэж байна";
        } catch (Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээллийг нээж үзэх үед алдаа гарч зогслоо байна';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {        
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = array('id' => $id, 'model' => LanguageModel::class);
            
            if (!$this->isUserCan('system_localization_update')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['code'])
                    || empty($payload['full'])
                ) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                $record = array(
                    'code' => $payload['code'],
                    'full' => $payload['full'],
                    'description' => $payload['description'] ?? null,
                    'is_default' => ($payload['is_default'] ?? 'off') != 'on' ? 0 : 1
                );
                $context['record'] = $record;
                $context['record']['id'] = $id;

                $defLanguage = $this->indosafe('/record?model=' . LanguageModel::class, array('is_default' => 1));
                if (isset($defLanguage['id']) && $record['is_default'] == 1) {
                    if ($defLanguage['id'] != $id) {
                        $this->indoput('/record?model=' . LanguageModel::class,
                            array('record' => array('is_default' => 0), 'condition' => ['WHERE' => "id={$defLanguage['id']}"]));
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
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, array('record' => $record))->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['full']} хэлний мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (Error $err) {
            $level = LogLevel::ERROR;
            $message = $err->getMessage();
            $context['error'] = array('code' => $err->getCode(), 'message' => $err->getMessage());
            throw new Exception($err->getMessage(), $err->getCode());
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
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
        try {
            $context = array('model' => LanguageModel::class);
            
            if (!$this->isUserCan('system_localization_delete')) {
                throw new Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                || !isset($payload['name'])
                || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $table = '';
            if (!empty($payload['table'])) {
                $table = "table={$payload['table']}&";
            }
            
            $id = filter_var($payload['id'], FILTER_VALIDATE_INT);
            
            $defLanguage = $this->indosafe("/record?{$table}model=" . LanguageModel::class, array('is_default' => 1));
            if (isset($defLanguage['id'])) {
                if ($defLanguage['id'] == $id) {
                    throw new Exception('Cannot remove default language!', 403);
                }
            }
            
            $this->indodelete("/record?{$table}model=" . LanguageModel::class, array('WHERE' => "id=$id"));
            
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
            ), $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function datatable()
    {        
        try {
            $rows = array();
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $languages = $this->indo('/record/rows?model=' . LanguageModel::class);
            foreach ($languages as $record) {
                $id = $record['id'];
                $row = array($record['code']);
                
                $row[] = htmlentities($record['full']);
                $row[] = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5.3/flags/' . $record['code'] . '.png">';
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
                'draw' => (int)($this->getQueryParams()['draw'] ?? 0)
            ));
        }
    }
}
