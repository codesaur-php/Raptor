<?php

namespace Raptor\Localization;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Localization\CountriesModel;

use Raptor\Dashboard\DashboardController;

class CountriesController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', []);
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

        $this->twigDashboard(\dirname(__FILE__) . '/countries-index.html')->render();
        
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
                
                $row[] = \htmlentities($record['content']['title'][$code]);
                $row[] = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5.3/flags/' . \strtolower($record['id']) . '.png">';
                $row[] = \htmlentities($record['speak']);
                
                $action = '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('country-view', ['id' => $record['id']]) . '"><i class="bi bi-eye"></i></a>' . \PHP_EOL;
                if ($this->getUser()->can('system_localization_update')) {
                    $action .= '<a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('country-update', ['id' => $record['id']]) . '"><i class="bi bi-pencil-square"></i></a>' . \PHP_EOL;
                }
                if ($this->getUser()->can('system_localization_delete')) {
                    $action .= '<a class="delete-country btn btn-sm btn-danger shadow-sm" href="' . $record['id'] . '"><i class="bi bi-trash"></i></a>';
                }
                $row[] = $action;
                
                $rows[] = $row;
            }
        } catch (\Throwable $th) {
            $this->errorLog($th);
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
                
                $this->indopost(
                    '/record?model=' . CountriesModel::class,
                    ['record' => $record, 'content' => $content]);
        
                $this->respondJSON([
                    'status' => 'success',
                    'href' => $this->generateLink('countries'),
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинэ улсын мэдээлэл [{$payload['id']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $template_path = \dirname(__FILE__) . '/country-insert-modal.html';
                if (!\file_exists($template_path)) {
                    throw new \Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, [
                    'language' => $this->getAttribute('localization')['language'] ?? []
                ])->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Улсын мэдээлэл үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $th) {
            if ($is_submit) {
                $this->respondJSON(['message' => $th->getMessage()], $th->getCode());
            } else {
                $this->modalProhibited($th->getMessage(), $th->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Улсын мэдээлэл үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $th->getCode(), 'message' => $th->getMessage()];
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
            
            $template_path = \dirname(__FILE__) . '/country-retrieve-modal.html';
            if (!\file_exists($template_path)) {
                throw new \Exception("$template_path file not found!", 500);
            }
            $this->twigTemplate($template_path, [
                'record' => $record, 'accounts' => $this->getAccounts(),
                'language' => $this->getAttribute('localization')['language'] ?? []
            ])->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['content']['title'][$this->getLanguageCode()]} улсын мэдээллийг нээж үзэж байна";
        } catch (\Throwable $th) {
            $this->modalProhibited($th->getMessage(), $th->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = "[$id] улсын мэдээллийг нээж үзэх үед алдаа гарч зогслоо";
            $context['error'] = ['code' => $th->getCode(), 'message' => $th->getMessage()];
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
                $this->indoput('/record?model=' . CountriesModel::class,
                    ['record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id='$id'"]]);
                
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
                
                $template_path = \dirname(__FILE__) . '/country-update-modal.html';
                if (!\file_exists($template_path)) {
                    throw new \Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, [
                    'record' => $record,
                    'language' => $this->getAttribute('localization')['language'] ?? []
                ])->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['content']['title'][$this->getLanguageCode()]} улсын мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Error $err) {
            $level = LogLevel::ERROR;
            $message = $err->getMessage();
            $context['error'] = ['code' => $err->getCode(), 'message' => $err->getMessage()];
            throw new \Exception($err->getMessage(), $err->getCode());
        } catch (\Throwable $th) {
            if ($is_submit) {
                $this->respondJSON(['message' => $th->getMessage()], $th->getCode());
            } else {
                $this->modalProhibited($th->getMessage(), $th->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $th->getCode(), 'message' => $th->getMessage()];
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
            $this->indodelete('/record?model=' . CountriesModel::class, ['WHERE' => "id='$id'"]);
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} улсын мэдээллийг устгалаа";
        } catch (\Throwable $th) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $th->getMessage()
            ], $th->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Улсын мэдээлэл устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $th->getCode(), 'message' => $th->getMessage()];
        } finally {
            $this->indolog('localization', $level, $message, $context);
        }
    }
}
