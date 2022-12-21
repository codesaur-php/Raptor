<?php

namespace Raptor\Localization;

use Error;
use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Localization\TextModel;

use Raptor\Dashboard\DashboardController;

class TextController extends DashboardController
{
    const RECORD_TYPE = array(0 => 'sys-defined', 1 => 'user-defined');

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
            'warning' => 4,
            'info' => 5,
            'dark' => 6
        );
        $names = $this->indoget('/text/table/names');
        $table = array('user', 'default', 'dashboard');
        foreach ($names as $name) {
            if (in_array($name, $table)) {
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
    
    public function datatable(string $table)
    {
        try {
            $rows = array();
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $texts = $this->indoget("/text/records/$table");
            $language = $this->getAttribute('localization')['language'] ?? array();
            foreach ($texts as $record) {
                $id = $record['id'];
                
                $row = array(htmlentities($record['keyword']));
                foreach (array_keys($language) as $code) {
                    $row[] = htmlentities($record['content']['text'][$code]);
                }
                $row[] = array(htmlentities(self::RECORD_TYPE[$record['type']] ?? $record['type']));
                $action = '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('text-view', array('table' => $table, 'id' => $id)) . '"><i class="bi bi-eye"></i></a>' . PHP_EOL;
                if ($this->getUser()->can('system_localization_update')) {
                    $action .= '<a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('text-update', array('table' => $table, 'id' => $id)) . '"><i class="bi bi-pencil-square"></i></a>' . PHP_EOL;
                }
                if ($this->getUser()->can('system_localization_delete')) {
                    $action .= '<a class="delete-text btn btn-sm btn-danger shadow-sm" href="' . "$table:$id" . '"><i class="bi bi-trash"></i></a>';
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
    
    public function insert(string $table)
    {        
        try {            
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = array('model' => TextModel::class, 'table' => $table);
            
            if (!$this->isUserCan('system_localization_insert')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $record = array();
                $content = array();
                $payload = $this->getParsedBody();
                foreach ($payload as $index => $value) {
                    if (is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                        }
                    } else {
                        $record[$index] = $value;
                    }
                }
                $context['payload'] = $payload;
                
                if (empty($record['keyword'])) {
                    throw new Exception($this->text('invalid-values'), 400);
                }
                
                $found = $this->indosafe('/text/find/keyword', array('keyword' => $record['keyword']), 'POST');
                if (!empty($found['table'])) {
                    throw new Exception(
                        $this->text('keyword-existing') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']);
                }
                
                $this->indopost("/text/$table", array('record' => $record, 'content' => $content));
        
                $this->respondJSON(array(
                    'status' => 'success',
                    'href' => $this->generateLink('texts'),
                    'message' => $this->text('record-insert-success')
                ));
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгт дээр шинэ текст [{$payload['keyword']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $template_path = dirname(__FILE__) . '/text-insert-modal.html';
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
    
    public function view(string $table, int $id)
    {        
        try {            
            $context = array('id' => $id, 'model' => TextModel::class, 'table' => $table);
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            $record = $this->indoget("/text/$table", array('p.id' => $id));
            $context['record'] = $record;
            
            $template_path = dirname(__FILE__) . '/text-retrieve-modal.html';
            if (!file_exists($template_path)) {
                throw new Exception("$template_path file not found!", 500);
            }
            $this->twigTemplate($template_path, array(
                'record_type' => self::RECORD_TYPE,
                'record' => $record, 'accounts' => $this->getAccounts(),
                'table' => $table, 'language' => $this->getAttribute('localization')['language'] ?? array()
            ))->render();

            $level = LogLevel::NOTICE;
            $message = "$table хүснэгтээс [{$record['keyword']}] текст мэдээллийг нээж үзэж байна";
        } catch (Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгтээс текст мэдээллийг нээж үзэх үед алдаа гарч зогслоо";
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = array('id' => $id, 'model' => TextModel::class, 'table' => $table);

            if (!$this->isUserCan('system_localization_update')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $record = array();
                $content = array();
                $payload = $this->getParsedBody();
                foreach ($payload as $index => $value) {
                    if (is_array($value)) {
                        foreach ($value as $key => $value) {
                            $content[$key][$index] = $value;
                        }
                    } else {
                        $record[$index] = $value;
                    }
                }
                $context['payload'] = $payload;
                
                if (empty($record['keyword'])) {
                    throw new Exception($this->text('invalid-request'), 400);
                }

                $found = $this->indosafe('/text/find/keyword', array('keyword' => $record['keyword']), 'POST');
                if (!empty($found)
                    && (
                        (int)$found['id'] != $id
                        || $found['table'] != "localization_text_$table"
                    )
                ) {
                    throw new Exception(
                        $this->text('keyword-existing') . ' -> ID = ' .
                        $found['id'] . ', Table = ' . $found['table']);
                }
                
                $this->indoput("/text/$table", array(
                    'record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id=$id"]));
                
                $this->respondJSON(array(
                    'type' => 'primary',
                    'status' => 'success',
                    'href' => $this->generateLink('texts'),
                    'message' => $this->text('record-update-success')
                ));
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгтийн [{$record['keyword']}] текст мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indoget("/text/$table", array('p.id' => $id));
                
                $template_path = dirname(__FILE__) . '/text-update-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, array(
                    'record' => $record, 'table' => $table,
                    'language' => $this->getAttribute('localization')['language'] ?? array()))->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "$table хүснэгтээс [{$record['keyword']}] текст мэдээллийг шинэчлэхээр нээж байна";
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
            $message = "$table хүснэгтээс текст мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {        
        try {
            $context = array('model' => TextModel::class);
            
            if (!$this->isUserCan('system_localization_delete')) {
                throw new Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['table'])
                || empty($payload['id'])
                || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $table = $payload['table'];
            $id = filter_var($payload['id'], FILTER_VALIDATE_INT);
            $this->indodelete("/text/$table", array('WHERE' => "id=$id"));
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
            
            $level = LogLevel::ALERT;
            $message = "$table хүснэгтээс [" . ($payload['name'] ?? $id) . '] текст мэдээллийг устгалаа';
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ), $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Текст мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
}
