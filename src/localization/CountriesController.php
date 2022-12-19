<?php

namespace Raptor\Localization;

use Error;
use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Localization\CountriesModel;

use Raptor\Dashboard\DashboardController;

class CountriesController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])
            && isset($localization['text']['countries'])
        ) {
            $meta['content']['title'][$localization['code']] = $localization['text']['world-countries'];
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

        $this->twigDashboard(dirname(__FILE__) . '/countries-index.html')->render();
        
        $this->indolog('localization', LogLevel::NOTICE, 'Дэлхийн улсуудын жагсаалтыг нээж үзэж байна', array('model' => CountriesModel::class));
    }
    
    public function datatable()
    {        
        try {
            $rows = array();
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $code = $this->getLanguageCode();
            $languages = $this->indo('/records?model=' . CountriesModel::class);
            foreach ($languages as $record) {
                $row = array($record['id']);
                
                $row[] = htmlentities($record['content']['title'][$code]);
                $row[] = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5.3/flags/' . strtolower($record['id']) . '.png">';
                $row[] = htmlentities($record['speak']);
                
                $action = '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('country-view', array('id' => $record['id'])) . '"><i class="bi bi-eye"></i></a>' . PHP_EOL;
                if ($this->getUser()->can('system_localization_update')) {
                    $action .= '<a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('country-update', array('id' => $record['id'])) . '"><i class="bi bi-pencil-square"></i></a>' . PHP_EOL;
                }
                if ($this->getUser()->can('system_localization_delete')) {
                    $action .= '<a class="delete-country btn btn-sm btn-danger shadow-sm" href="' . $record['id'] . '"><i class="bi bi-trash"></i></a>';
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
    
    public function insert()
    {        
        try {            
            $context = array('model' => CountriesModel::class);
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
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
                
                if (empty($payload['id']) || empty($payload['speak'])) {
                    throw new Exception($this->text('invalid-values'), 400);
                }
                
                $this->indopost(
                    '/record?model=' . CountriesModel::class,
                    array('record' => $record, 'content' => $content));
        
                $this->respondJSON(array(
                    'status' => 'success',
                    'href' => $this->generateLink('countries'),
                    'message' => $this->text('record-insert-success')
                ));
                
                $level = LogLevel::INFO;
                $message = "Шинэ улсын мэдээлэл [{$payload['id']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $template_path = dirname(__FILE__) . '/country-insert-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, array(
                    'language' => $this->getAttribute('localization')['language'] ?? array()))->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Улсын мэдээлэл үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Улсын мэдээлэл үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function view($id)
    {        
        try {            
            $context = array('id' => $id, 'model' => CountriesModel::class);
            
            if (!$this->isUserCan('system_localization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indo('/record?model=' . CountriesModel::class, array('p.id' => $id));
            $context['record'] = $record;
            
            $template_path = dirname(__FILE__) . '/country-retrieve-modal.html';
            if (!file_exists($template_path)) {
                throw new Exception("$template_path file not found!", 500);
            }
            $this->twigTemplate($template_path, array(
                'record' => $record, 'accounts' => $this->getAccounts(),
                'language' => $this->getAttribute('localization')['language'] ?? array()))->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['content']['title'][$this->getLanguageCode()]} улсын мэдээллийг нээж үзэж байна";
        } catch (Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "[$id] улсын мэдээллийг нээж үзэх үед алдаа гарч зогслоо";
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function update($id)
    {        
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = array('id' => $id, 'model' => CountriesModel::class);
            
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
                if (empty($payload['id']) || empty($payload['speak'])) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                
                $id = preg_replace('/[^A-Za-z]/', '', $payload['id']);
                $this->indoput('/record?model=' . CountriesModel::class,
                    array('record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id='$id'"]));
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('countries')
                ));
                
                $level = LogLevel::INFO;
                $message = "{$record['id']} улсын мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indo('/record?model=' . CountriesModel::class, array('p.id' => $id));
                
                $template_path = dirname(__FILE__) . '/country-update-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, array('record' => $record,
                    'language' => $this->getAttribute('localization')['language'] ?? array()))->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['content']['title'][$this->getLanguageCode()]} улсын мэдээллийг шинэчлэхээр нээж байна";
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
            $message = "[$id] улсын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
    
    public function delete()
    {        
        try {
            $context = array('model' => CountriesModel::class);
            
            if (!$this->isUserCan('system_localization_delete')) {
                throw new Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id']) || !isset($payload['name'])) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = preg_replace('/[^A-Za-z]/', '', $payload['id']);
            $this->indodelete('/record?model=' . CountriesModel::class, array('WHERE' => "id='$id'"));
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} улсын мэдээллийг устгалаа";
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ), $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Улсын мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
}
