<?php

namespace Raptor\Localization;

use Error;
use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Localization\TextModel;
use Indoraptor\Localization\CountriesModel;

use Raptor\Dashboard\DashboardController;

class TextController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])) {
            $meta['content']['title'][$localization['code']] = $localization['code'] == 'mn' ? 'Текстүүд' : 'Texts';
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
        
        $colors = array(
            'success' => 1,
            'primary' => 2,
            'danger' => 3,
            'success' => 4,
            'warning' => 5,
            'info' => 6,
            'dark' => 7
        );
        $names = $this->indoget('/text');
        $table = array('user', 'dashboard', 'default');
        foreach ($names as $key => $name) {
            if (in_array($name, $table)) {
                unset($names[$key]);
            } else {
                $table[] = $name;
            }
        }
        $tables = array_flip($table);
        foreach ($tables as &$index) {
            $index = array_rand($colors);
            if (count($colors) > 1) {
                unset($colors[$index]);
            }
        }
        $this->twigDashboard(dirname(__FILE__) . '/texts-index.html', array(
            'tables' => $tables, 'localization' => $this->getAttribute('localization')))->render();
        
        $this->indolog('localization', LogLevel::NOTICE, 'Системийн текст жагсаалтыг нээж үзэж байна', array('model' => TextModel::class, 'tables' => $tables));
    }
    
    public function datatable($table)
    {
        $rows = array();
        
        try {
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $texts = $this->indo('/record/rows?model=' . TextModel::class . "&table=$table");
            $language = $this->getAttribute('localization')['language'] ?? array();
            $current_code = $this->getLanguageCode();
            $types = $this->indo('/lookup', array(
                'table' => 'record_type', 'condition' => array('WHERE' => "c.code='$current_code' AND p.is_active=1")));
            foreach ($texts as $record) {
                $id = $record['id'];
                
                $row = array(htmlentities($record['keyword']));
                foreach (array_keys($language) as $code) {
                    $row[] = htmlentities($record['content']['text'][$code]);
                }
                $row[] = array(htmlentities($types[$record['type']]['title'][$current_code] ?? $record['type']));
                $action = '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('texts-view', array('table' => $table, 'id' => $id)) . '"><i class="bi bi-eye"></i></a>' . PHP_EOL;
                if ($this->getUser()->can('system_localization_update')) {
                    $action .= '<a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('texts-update', array('table' => $table, 'id' => $id)) . '"><i class="bi bi-pencil-square"></i></a>' . PHP_EOL;
                }
                if ($this->getUser()->can('system_localization_delete')) {
                    $action .= '<a class="delete-texts btn btn-sm btn-danger shadow-sm" href="' . $table . ':' . $id . '"><i class="bi bi-trash"></i></a>';
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
    
    public function insert($table)
    {
        $context = array('model' => LanguageModel::class);
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        
        try {            
            if (!$this->isUserCan('system_localization_insert')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['keyword'])) {
                    throw new Exception($this->text('invalid-values'), 400);
                }
                $context['payload'] = $payload;
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('texts')
                ));
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгт дээр шинэ текст [{$payload['keyword']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $template_path = dirname(__FILE__) . '/texts-insert-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, array('table' => $table,
                    'language' => $this->getAttribute('localization')['language'] ?? array()))->render();
                
                $level = LogLevel::NOTICE   ;
                $message = "$table хүснэгт дээр шинэ текст үүсгэх үйлдлийг эхлүүллээ";
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгт дээр шинэ текст үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
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
        $is_submit = $this->getRequest()->getMethod() == 'PUT';
        $context = array('id' => $id, 'model' => LanguageModel::class);
        
        try {
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
        $context = array('model' => LanguageModel::class);
        
        try {
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
            
            $defLanguage = $this->indosafe("/record?{$table}model=" . LanguageModel::class, array('is_default' => 1));
            if (isset($defLanguage['id'])) {
                if ($defLanguage['id'] == $payload['id']) {
                    throw new Exception('Cannot remove default language!', 403);
                }
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
            ), $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хэлний мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
}
