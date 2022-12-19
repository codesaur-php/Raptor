<?php

namespace Raptor\Contents;

use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Contents\ReferenceModel;
use Indoraptor\Contents\ReferenceInitial;

use Raptor\Dashboard\DashboardController;

class ReferencesController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])
            && isset($localization['text']['reference-tables'])
        ) {
            $meta['content']['title'][$localization['code']] = $localization['text']['reference-tables'];
            $request = $request->withAttribute('meta', $meta);
        }
        
        parent::__construct($request);
    }
    
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $reference_likes = $this->indosafe('/statement', array('query' => "SHOW TABLES LIKE 'reference_%'"));
        $names = array();
        foreach ($reference_likes as $name) {
            $names[] = reset($name);
        }
        $references = array();
        foreach ($names as $name) {
            if (in_array($name . '_content', $names)) {
                $references[] = substr($name, strlen('reference_'));
            }
        }
        $initials = get_class_methods(ReferenceInitial::class);
        foreach ($initials as $value) {
            $initial = substr($value, strlen('reference_'));
            if (!in_array($initial, $references)) {
                $references[] = $initial;
            }
        }       
        $tables = array('templates');
        foreach ($references as $reference) {
            if (!in_array($reference, $tables)) {
                $tables[] = $reference;
            }
        }
        
        $this->twigDashboard(dirname(__FILE__) . '/references-index.html', array(
            'tables' => $tables, 'language' => $this->getAttribute('localization')['language'] ?? array()))->render();
        
        $this->indolog('content', LogLevel::NOTICE, 'Лавлах хүснэгтүүдийн жагсаалтыг нээж үзэж байна', array('model' => ReferenceModel::class));
    }
    
    public function datatable(string $table)
    {
        try {
            $rows = array();
            
            if (!$this->isUserCan('system_content_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $status = $this->getStatusValues();
            $language = $this->getAttribute('localization')['language'] ?? array();
            $records = $this->indo("/reference/records/$table", array('WHERE' => 'is_active<>999'));
            foreach ($records as $record) {
                $id = $record['id'];
                $row = array($id);
                
                $row[] = htmlentities($record['keyword']);
                foreach (array_keys($language) as $code) {
                    $row[] = htmlentities($record['content']['title'][$code]);
                }
                $row[] = htmlentities($record['category']);
                $row[] = htmlentities($status[$record['is_active']] ?? $record['is_active']);
                
                $action = '';
                if ($this->getUser()->can('system_content_index')) {
                    $action .=
                        '<a class="btn btn-sm btn-info shadow-sm" href="' .
                        $this->generateLink('reference-view', array('table' => $table, 'id' => $id)) .
                        '"><i class="bi bi-eye"></i></a>' . PHP_EOL;
                }
                if ($this->getUser()->can('system_content_update')) {
                    $action .=
                        '<a class="btn btn-sm btn-primary shadow-sm" href="' .
                        $this->generateLink('reference-update', array('table' => $table, 'id' => $id)) .
                        '"><i class="bi bi-pencil-square"></i></a>' . PHP_EOL;
                }
                if ($this->getUser()->can('system_content_delete')) {
                    $action .=
                        '<a class="delete-reference btn btn-sm btn-danger shadow-sm" href="' .
                        "$table:$id" . '"><i class="bi bi-trash"></i></a>';
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
    
    public function getCategoryValues()
    {
        if ($this->getLanguageCode() == 'mn') {
            return array(
                'general' => 'Ерөнхий',
                'system' => 'Систем',
                'manual' => 'Заавар',
                'notification' => 'Сонордуулга',
                'email' => 'Цахим захиа'
            );
        } else {
            return array(
                'general' => 'General',
                'system' => 'System',
                'manual' => 'Manual',
                'notification' => 'Notification',
                'email' => 'Email'
            );
        }
    }
    
    public function insert(string $table)
    {        
        try {            
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            $context = array('model' => ReferenceModel::class, 'table' => $table);
            
            if (!$this->isUserCan('system_content_insert')) {
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
                
                if (empty($payload['keyword'])
                    || empty($payload['category'])
                ) {
                    throw new Exception($this->text('invalid-values'), 400);
                }
                
                $id = $this->indopost("/reference/$table", array('record' => $record, 'content' => $content));
               
                $context['record'] = $id;
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('references')
                ));
                
                $level = LogLevel::INFO;
                $message = "Шинээр [{$payload['keyword']}] түлхүүртэй лавлах мэдээллийг [$table] хүснэгт дээр үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $template_path = dirname(__FILE__) . '/reference-insert.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigDashboard($template_path, array(
                    'table' => $table,
                    'status' => $this->getStatusValues(),
                    'category' => $this->getCategoryValues(),
                    'language' => $this->getAttribute('localization')['language'] ?? array()))->render();
                
                $level = LogLevel::NOTICE;
                $message = "Шинэ лавлах мэдээллийг [$table] хүснэгт дээр үйлдлийг эхлүүллээ";
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = "Шинэ лавлах мэдээллийг [$table] хүснэгтэд үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function view(string $table, int $id)
    {        
        try {            
            $context = array('id' => $id, 'model' => ReferenceModel::class, 'table' => $table);
            
            if (!$this->isUserCan('system_content_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $records = $this->indo("/reference/records/$table", array('WHERE' => "p.id=$id"));
            $record = reset($records);
            $context['record'] = $record;
            if (empty($record)) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            
            $template_path = dirname(__FILE__) . '/reference-view.html';
            if (!file_exists($template_path)) {
                throw new Exception("$template_path file not found!", 500);
            }
            $this->twigDashboard($template_path, array(
                'table' => $table,
                'record' => $record,
                'status' => $this->getStatusValues(),
                'category' => $this->getCategoryValues(),
                'language' => $this->getAttribute('localization')['language'] ?? array(),
                'accounts' => $this->getAccounts()))->render();

            $level = LogLevel::NOTICE;
            $message = "$table хүснэгтийн $id дугаартай [{$record['keyword']}] түлхүүртэй лавлах мэдээллийг нээж үзэж байна";
        } catch (Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "$table хүснэгтээс $id дугаартай лавлах мэдээллийг нээж үзэх үед алдаа гарч зогслоо байна";
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function update(string $table, int $id)
    {        
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = array('id' => $id, 'model' => ReferenceModel::class, 'table' => $table);
            
            if (!$this->isUserCan('system_content_update')) {
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
                if (empty($payload)) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                
                $this->indoput("/reference/$table", array(
                    'record' => $record, 'content' => $content, 'condition' => ['WHERE' => "id=$id"]));
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('references')
                ));
                
                $level = LogLevel::INFO;
                $message = "$table хүснэгтийн $id дугаартай [{$record['keyword']}] түлхүүртэй лавлах мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $records = $this->indo("/reference/records/$table", array('WHERE' => "p.id=$id"));
                $record = reset($records);
                if (empty($record)) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                $context['record'] = $record;
                
                $template_path = dirname(__FILE__) . '/reference-update.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigDashboard($template_path, array(
                    'table' => $table,
                    'record' => $record,
                    'status' => $this->getStatusValues(),
                    'category' => $this->getCategoryValues(),
                    'language' => $this->getAttribute('localization')['language'] ?? array(),
                    'accounts' => $this->getAccounts()))->render();
                
                $level = LogLevel::NOTICE;
                $message = "$table хүснэгтийн $id дугаартай [{$record['keyword']}] түлхүүртэй лавлах мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            $level = LogLevel::ERROR;
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            $message = "$table хүснэгтийн $id дугаартай лавлах мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function delete()
    {        
        try {
            $context = array('model' => ReferenceModel::class);
            
            if (!$this->isUserCan('system_content_delete')) {
                throw new Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            $context['payload'] = $payload;
            if (empty($payload['id'])
                || !isset($payload['name'])
                || empty($payload['table'])
                || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            
            $table = $payload['table'];
            $id = filter_var($payload['id'], FILTER_VALIDATE_INT);
            $this->indodelete("/reference/$table", array('WHERE' => "id=$id"));
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
            
            $level = LogLevel::ALERT;
            $message = "$table хүснэгтээс $id дугаартай [{$payload['name']}] түлхүүртэй лавлах мэдээллийг устгалаа";
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ), $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Лавлах мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
}
